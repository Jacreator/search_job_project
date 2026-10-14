<?php

declare(strict_types=1);

use App\Enums\SponsorStatus;
use App\Enums\TeamRole;
use App\Enums\TechReason;
use App\Enums\VisaSponsorship;
use App\Jobs\EnrichSponsor;
use App\Jobs\FindCareersPage;
use App\Models\Sponsor;
use App\Models\Team;
use App\Models\User;
use App\Services\HostResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

const CAREERS_SITE = 'https://www.acmesoftware.co.uk';

const CAREERS_PAGE_TEXT = '<h1>Join us</h1><p>We are hiring Software Engineers. Visa sponsorship available.</p>';

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config(['sponsor-finder.batch_size' => 100]);
    app()->instance(HostResolver::class, new class extends HostResolver
    {
        public function addresses(string $host): array
        {
            return ['93.184.215.14'];
        }
    });
});

/**
 * Fake the given pages. Anything else answers 404.
 *
 * @param  array<string, string|int>  $pages  URL => HTML body, or a status code
 */
function fakeSite(array $pages): void
{
    $fakes = [];

    foreach ($pages as $url => $page) {
        $fakes[$url] = is_int($page) ? Http::response('', $page) : Http::response($page);
    }

    Http::fake([...$fakes, '*' => Http::response('Not found', 404)]);
}

function homepageWith(string $links): string
{
    return "<html><body><nav>{$links}</nav><p>Acme Software builds apps.</p></body></html>";
}

/**
 * A finished sponsor with a confident website, ready for the careers check.
 *
 * @param  array<string, mixed>  $attributes
 */
function careersSponsor(array $attributes = []): Sponsor
{
    return Sponsor::factory()->create([
        'name' => 'Acme Software Ltd',
        'status' => SponsorStatus::Done,
        'website' => CAREERS_SITE,
        'confidence' => 80,
        ...$attributes,
    ]);
}

function checkCareers(Sponsor $sponsor): Sponsor
{
    FindCareersPage::dispatch($sponsor);

    return $sponsor->refresh();
}

/**
 * @return list<string>
 */
function fetchedUrls(): array
{
    return Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->values()->all();
}

function careersTeamUser(Team $team): User
{
    $user = User::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    return $user;
}

/**
 * @return list<int>
 */
function queuedCareersIds(): array
{
    return Queue::pushed(FindCareersPage::class)
        ->map(fn (FindCareersPage $job) => $job->sponsor->id)
        ->values()
        ->all();
}

/**
 * @param  array<string, mixed>  $options
 * @return list<list<string>>
 */
function exportCareersRows(Team $team, array $options = []): array
{
    test()->artisan('sponsors:export', ['--team' => $team->slug, ...$options])->assertSuccessful();

    $path = storage_path('app/exports/'.$team->slug.'_tech_sponsors_'.now()->format('Y-m-d').'.csv');
    $lines = array_map(str_getcsv(...), file($path, FILE_IGNORE_NEW_LINES) ?: []);
    @unlink($path);

    return $lines;
}

test('the migration adds the careers fields and rolls back', function () {
    $sponsor = Sponsor::factory()->create()->refresh();

    expect($sponsor->careers_url)->toBeNull()
        ->and($sponsor->mentions_developer_roles)->toBeNull()
        ->and($sponsor->visa_sponsorship)->toBeNull()
        ->and($sponsor->careers_checked_at)->toBeNull();

    $columns = ['careers_url', 'mentions_developer_roles', 'visa_sponsorship', 'careers_checked_at'];
    $migration = require database_path('migrations/2026_10_14_000005_add_careers_fields_to_sponsors_table.php');

    $migration->down();
    expect(Schema::hasColumns('sponsors', $columns))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumns('sponsors', $columns))->toBeTrue();
});

describe('finding the page', function () {
    test('finds a careers link on the homepage and reads its signals', function () {
        fakeSite([
            CAREERS_SITE => homepageWith('<a href="/about">About</a><a href="/careers">Careers</a>'),
            CAREERS_SITE.'/careers' => CAREERS_PAGE_TEXT,
        ]);

        $sponsor = checkCareers(careersSponsor());

        expect($sponsor->careers_url)->toBe(CAREERS_SITE.'/careers')
            ->and($sponsor->mentions_developer_roles)->toBeTrue()
            ->and($sponsor->visa_sponsorship)->toBe(VisaSponsorship::Offered)
            ->and($sponsor->careers_checked_at)->not->toBeNull()
            ->and($sponsor->status)->toBe(SponsorStatus::Done)
            ->and(fetchedUrls())->toBe([CAREERS_SITE, CAREERS_SITE.'/careers']);
    });

    test('finds a link by its path when the text says nothing', function () {
        fakeSite([
            CAREERS_SITE => homepageWith('<a href="/life-at-acme">Our people</a>'),
            CAREERS_SITE.'/life-at-acme' => '<p>We are unable to offer visa sponsorship.</p>',
        ]);

        $sponsor = checkCareers(careersSponsor());

        expect($sponsor->careers_url)->toBe(CAREERS_SITE.'/life-at-acme')
            ->and($sponsor->mentions_developer_roles)->toBeFalse()
            ->and($sponsor->visa_sponsorship)->toBe(VisaSponsorship::NotOffered);
    });

    test('finds a job board link, including a Workday subdomain', function (string $url) {
        fakeSite([
            CAREERS_SITE => homepageWith("<a href=\"{$url}\">Apply</a>"),
            $url => CAREERS_PAGE_TEXT,
        ]);

        expect(checkCareers(careersSponsor())->careers_url)->toBe($url);
    })->with([
        'workday subdomain' => 'https://acme.wd3.myworkdayjobs.com/External',
        'greenhouse' => 'https://boards.greenhouse.io/acme',
        'bare host' => 'https://breezy.hr/acme',
    ]);

    test('finds new link wording', function (string $text) {
        fakeSite([
            CAREERS_SITE => homepageWith("<a href=\"/team\">{$text}</a>"),
            CAREERS_SITE.'/team' => CAREERS_PAGE_TEXT,
        ]);

        expect(checkCareers(careersSponsor())->careers_url)->toBe(CAREERS_SITE.'/team');
    })->with([
        'open roles' => 'Open roles',
        'curly apostrophe' => "We\u{2019}re hiring",
        'straight apostrophe' => "We're hiring",
    ]);

    test('resolves relative and absolute links on the same site', function (string $href, string $expected) {
        fakeSite([
            CAREERS_SITE.'/about/' => homepageWith("<a href=\"{$href}\">Careers</a>"),
            $expected => CAREERS_PAGE_TEXT,
        ]);

        expect(checkCareers(careersSponsor(['website' => CAREERS_SITE.'/about/']))->careers_url)->toBe($expected);
    })->with([
        'relative' => ['jobs.html', CAREERS_SITE.'/about/jobs.html'],
        'root relative' => ['/careers', CAREERS_SITE.'/careers'],
        'with a fragment' => ['/careers#open', CAREERS_SITE.'/careers'],
        'subdomain' => ['https://careers.acmesoftware.co.uk/', 'https://careers.acmesoftware.co.uk/'],
        'without www' => ['https://acmesoftware.co.uk/careers', 'https://acmesoftware.co.uk/careers'],
    ]);

    test('ignores links to other sites, mail links, and links back to the homepage', function () {
        fakeSite([
            CAREERS_SITE => homepageWith(
                '<a href="https://uk.linkedin.com/company/acme/jobs">Careers</a>'
                .'<a href="mailto:jobs@acmesoftware.co.uk">Jobs</a>'
                .'<a href="#careers">Careers</a>'
                .'<a href="/">Join us</a>'
            ),
            CAREERS_SITE.'/careers' => CAREERS_PAGE_TEXT,
        ]);

        expect(checkCareers(careersSponsor())->careers_url)->toBe(CAREERS_SITE.'/careers')
            ->and(fetchedUrls())->toBe([CAREERS_SITE, CAREERS_SITE.'/careers']);
    });

    test('falls back to common paths when no link works', function () {
        fakeSite([
            CAREERS_SITE => homepageWith('<a href="/vacancies">Vacancies</a>'),
            CAREERS_SITE.'/vacancies' => 500,
            CAREERS_SITE.'/jobs' => CAREERS_PAGE_TEXT,
        ]);

        $sponsor = checkCareers(careersSponsor());

        expect($sponsor->careers_url)->toBe(CAREERS_SITE.'/jobs')
            ->and(fetchedUrls())->toBe([
                CAREERS_SITE,
                CAREERS_SITE.'/vacancies',
                CAREERS_SITE.'/careers',
                CAREERS_SITE.'/jobs',
            ]);
    });

    test('tries the common paths when the homepage fails', function () {
        fakeSite([
            CAREERS_SITE => 503,
            CAREERS_SITE.'/work-with-us' => CAREERS_PAGE_TEXT,
        ]);

        expect(checkCareers(careersSponsor())->careers_url)->toBe(CAREERS_SITE.'/work-with-us');
    });

    test('the first 200 wins and other responses are skipped', function () {
        fakeSite([
            CAREERS_SITE => homepageWith('<a href="/jobs-old">Jobs</a><a href="/join">Join</a><a href="/opportunities">Opportunities</a>'),
            CAREERS_SITE.'/jobs-old' => 410,
            CAREERS_SITE.'/join' => CAREERS_PAGE_TEXT,
            CAREERS_SITE.'/opportunities' => CAREERS_PAGE_TEXT,
        ]);

        $sponsor = checkCareers(careersSponsor());

        expect($sponsor->careers_url)->toBe(CAREERS_SITE.'/join')
            ->and(fetchedUrls())->not->toContain(CAREERS_SITE.'/opportunities');
    });

    test('tries at most five homepage links', function () {
        $links = implode('', array_map(fn (int $i) => "<a href=\"/jobs-{$i}\">Jobs {$i}</a>", range(1, 8)));
        fakeSite([CAREERS_SITE => homepageWith($links)]);

        checkCareers(careersSponsor());

        expect(fetchedUrls())->toBe([
            CAREERS_SITE,
            ...array_map(fn (int $i) => CAREERS_SITE."/jobs-{$i}", range(1, 5)),
            ...array_map(fn (string $path) => CAREERS_SITE.$path, ['/careers', '/jobs', '/join-us', '/work-with-us']),
        ]);
    });

    test('saves nulls but marks the row checked when nothing is found', function () {
        fakeSite([CAREERS_SITE => homepageWith('<a href="/about">About</a>')]);

        $sponsor = checkCareers(careersSponsor());

        expect($sponsor->careers_url)->toBeNull()
            ->and($sponsor->mentions_developer_roles)->toBeNull()
            ->and($sponsor->visa_sponsorship)->toBeNull()
            ->and($sponsor->careers_checked_at)->not->toBeNull()
            ->and($sponsor->status)->toBe(SponsorStatus::Done);
    });

    test('never fetches a private address', function () {
        app()->instance(HostResolver::class, new class extends HostResolver
        {
            public function addresses(string $host): array
            {
                return ['127.0.0.1'];
            }
        });
        fakeSite([]);

        $sponsor = checkCareers(careersSponsor());

        Http::assertNothingSent();
        expect($sponsor->careers_url)->toBeNull()
            ->and($sponsor->careers_checked_at)->not->toBeNull();
    });
});

describe('the job', function () {
    test('does nothing for rows that are not eligible', function (array $attributes) {
        fakeSite([]);

        $sponsor = checkCareers(careersSponsor($attributes));

        Http::assertNothingSent();
        expect($sponsor->careers_checked_at)->toBeNull();
    })->with([
        'confidence below 50' => [['confidence' => 49]],
        'no confidence' => [['confidence' => null]],
        'no website' => [['website' => null]],
        'not done' => [['status' => SponsorStatus::Skipped]],
    ]);

    test('runs on a confidence of exactly 50', function () {
        fakeSite([CAREERS_SITE.'/careers' => CAREERS_PAGE_TEXT, CAREERS_SITE => homepageWith('')]);

        expect(checkCareers(careersSponsor(['confidence' => 50]))->careers_url)->toBe(CAREERS_SITE.'/careers');
    });

    test('running twice changes nothing and fetches nothing more', function () {
        fakeSite([CAREERS_SITE => homepageWith('<a href="/careers">Careers</a>'), CAREERS_SITE.'/careers' => CAREERS_PAGE_TEXT]);
        $sponsor = checkCareers(careersSponsor());
        $before = $sponsor->getAttributes();
        $sent = Http::recorded()->count();

        expect(checkCareers($sponsor)->getAttributes())->toBe($before)
            ->and(Http::recorded()->count())->toBe($sent);
    });

    test('a failed job marks the row checked with no result', function () {
        $sponsor = careersSponsor();

        (new FindCareersPage($sponsor))->failed();

        expect($sponsor->refresh()->careers_checked_at)->not->toBeNull()
            ->and($sponsor->careers_url)->toBeNull()
            ->and($sponsor->status)->toBe(SponsorStatus::Done);
    });

    test('is unique per sponsor and retries like the enrich job', function () {
        $sponsor = careersSponsor();
        $job = new FindCareersPage($sponsor);

        expect($job)->toBeInstanceOf(ShouldBeUnique::class)
            ->and($job->uniqueId())->toBe((string) $sponsor->id)
            ->and($job->uniqueFor)->toBe(86400)
            ->and($job->maxExceptions)->toBe(3)
            ->and($job->backoff)->toBe([60, 300, 900])
            ->and(property_exists($job, 'tries'))->toBeFalse()
            ->and($job->retryUntil()->diffInSeconds(now(), true))->toBeGreaterThan(86390);
    });

    test('uses the careers-pages rate limiter from config', function () {
        config(['sponsor-finder.careers_rate_per_minute' => 7]);

        $middleware = (new FindCareersPage(careersSponsor()))->middleware();
        $limit = RateLimiter::limiter('careers-pages')(new FindCareersPage(careersSponsor()));

        expect($middleware)->toHaveCount(1)
            ->and($middleware[0])->toBeInstanceOf(RateLimited::class)
            ->and($limit)->toBeInstanceOf(Limit::class)
            ->and($limit->maxAttempts)->toBe(7)
            ->and($limit->decaySeconds)->toBe(60);
    });
});

describe('the command', function () {
    beforeEach(fn () => Queue::fake());

    test('queues eligible unchecked rows, highest priority first', function () {
        $low = careersSponsor(['priority' => 10]);
        $high = careersSponsor(['priority' => 100]);
        $sameAsLow = careersSponsor(['priority' => 10]);
        careersSponsor(['careers_checked_at' => now()]);
        careersSponsor(['confidence' => 49]);
        careersSponsor(['website' => null]);
        careersSponsor(['status' => SponsorStatus::Pending]);
        careersSponsor(['status' => SponsorStatus::Failed]);

        $this->artisan('sponsors:careers')
            ->expectsOutput('Queued 3 sponsors for a careers page check.')
            ->assertSuccessful();

        expect(queuedCareersIds())->toBe([$high->id, $low->id, $sameAsLow->id]);
    });

    test('respects --limit and the batch size', function () {
        config(['sponsor-finder.batch_size' => 2]);
        $sponsors = collect(range(1, 3))->map(fn () => careersSponsor());

        $this->artisan('sponsors:careers')->assertSuccessful();
        expect(queuedCareersIds())->toBe($sponsors->take(2)->pluck('id')->all());

        Queue::fake();
        $this->artisan('sponsors:careers', ['--limit' => 1])
            ->expectsOutput('Queued 1 sponsor for a careers page check.')
            ->assertSuccessful();
        expect(queuedCareersIds())->toBe([$sponsors->first()->id]);
    });

    test('respects --team', function () {
        $team = Team::factory()->create();
        $mine = careersSponsor(['team_id' => $team->id]);
        careersSponsor();

        $this->artisan('sponsors:careers', ['--team' => $team->slug])->assertSuccessful();

        expect(queuedCareersIds())->toBe([$mine->id]);
    });

    test('fails on an unknown team or a bad limit', function (array $options, string $message) {
        $this->artisan('sponsors:careers', $options)->expectsOutput($message)->assertFailed();

        Queue::assertNothingPushed();
    })->with([
        'unknown team' => [['--team' => 'nope'], 'Team [nope] does not exist.'],
        'zero limit' => [['--limit' => '0'], 'The --limit option must be a whole number above 0.'],
        'text limit' => [['--limit' => 'ten'], 'The --limit option must be a whole number above 0.'],
    ]);

    test('is scheduled hourly without overlapping', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'sponsors:careers'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 * * * *')
            ->and($event->withoutOverlapping)->toBeTrue();
    });
});

test('shared lookups copy the careers fields', function () {
    $checkedAt = now()->subDay()->startOfSecond();
    Sponsor::factory()->for(Team::factory()->create(['share_lookups' => true]))->create([
        'name' => 'Acme Software Ltd',
        'town' => 'Sheffield',
        'status' => SponsorStatus::Done,
        'company_status' => 'active',
        'is_tech' => true,
        'tech_reason' => TechReason::Sic,
        'website' => CAREERS_SITE,
        'confidence' => 80,
        'careers_url' => CAREERS_SITE.'/careers',
        'mentions_developer_roles' => true,
        'visa_sponsorship' => VisaSponsorship::Offered,
        'careers_checked_at' => $checkedAt,
    ]);
    $target = Sponsor::factory()->for(Team::factory()->create(['share_lookups' => true]))->create([
        'name' => 'Acme Software Ltd',
        'town' => 'Sheffield',
    ]);

    EnrichSponsor::dispatch($target);
    $target->refresh();

    Http::assertNothingSent();
    expect($target->careers_url)->toBe(CAREERS_SITE.'/careers')
        ->and($target->mentions_developer_roles)->toBeTrue()
        ->and($target->visa_sponsorship)->toBe(VisaSponsorship::Offered)
        ->and($target->careers_checked_at?->equalTo($checkedAt))->toBeTrue();
});

describe('the dashboard', function () {
    test('shows the careers fields and filters by them', function () {
        $team = Team::factory()->create();
        $offered = careersSponsor(['team_id' => $team->id, 'name' => 'Offered Ltd', 'careers_url' => CAREERS_SITE.'/careers', 'mentions_developer_roles' => true, 'visa_sponsorship' => VisaSponsorship::Offered, 'careers_checked_at' => now()]);
        careersSponsor(['team_id' => $team->id, 'name' => 'Refused Ltd', 'mentions_developer_roles' => false, 'visa_sponsorship' => VisaSponsorship::NotOffered, 'careers_checked_at' => now()]);
        careersSponsor(['team_id' => $team->id, 'name' => 'Unknown Ltd', 'careers_checked_at' => now()]);
        careersSponsor(['team_id' => $team->id, 'name' => 'Unchecked Ltd']);
        $user = careersTeamUser($team);

        $names = fn (array $query): array => array_column(
            $this->actingAs($user)->get(route('sponsors.index', ['current_team' => $team->slug, ...$query]))->assertOk()->inertiaProps('sponsors.data'),
            'name',
        );

        expect($names(['developer_roles' => 1]))->toBe(['Offered Ltd'])
            ->and($names(['visa_sponsorship' => 'offered']))->toBe(['Offered Ltd'])
            ->and($names(['visa_sponsorship' => 'not_offered']))->toBe(['Refused Ltd'])
            ->and($names(['visa_sponsorship' => 'unknown']))->toBe(['Unknown Ltd']);

        $this->actingAs($user)
            ->get(route('sponsors.index', ['current_team' => $team->slug, 'visa_sponsorship' => 'offered', 'developer_roles' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.developer_roles', true)
                ->where('filters.visa_sponsorship', 'offered')
                ->where('sponsors.data.0.id', $offered->id)
                ->where('sponsors.data.0.careers_url', CAREERS_SITE.'/careers')
                ->where('sponsors.data.0.mentions_developer_roles', true)
                ->where('sponsors.data.0.visa_sponsorship', 'offered'));
    });

    test('rejects invalid careers filters', function (string $key, mixed $value) {
        $team = Team::factory()->create();

        $this->actingAs(careersTeamUser($team))
            ->get(route('sponsors.index', ['current_team' => $team->slug, $key => $value]))
            ->assertSessionHasErrors($key);
    })->with([
        'visa value' => ['visa_sponsorship', 'maybe'],
        'developer roles value' => ['developer_roles', 'maybe'],
    ]);
});

describe('the export', function () {
    test('has the careers columns', function () {
        $team = Team::factory()->create();
        careersSponsor(['team_id' => $team->id, 'name' => 'Offered Ltd', 'confidence' => 90, 'careers_url' => CAREERS_SITE.'/careers', 'mentions_developer_roles' => true, 'visa_sponsorship' => VisaSponsorship::Offered]);
        careersSponsor(['team_id' => $team->id, 'name' => 'Refused Ltd', 'confidence' => 80, 'mentions_developer_roles' => false, 'visa_sponsorship' => VisaSponsorship::NotOffered]);
        careersSponsor(['team_id' => $team->id, 'name' => 'Unchecked Ltd', 'confidence' => 70]);

        $lines = exportCareersRows($team);
        $columns = array_map(fn (string $title) => array_search($title, $lines[0], true), ['Careers URL', 'Developer roles', 'Visa sponsorship']);
        $cells = fn (array $line): array => array_map(fn (int $column) => $line[$column], $columns);

        expect($columns)->not->toContain(false)
            ->and($cells($lines[1]))->toBe([CAREERS_SITE.'/careers', 'Yes', 'Offered'])
            ->and($cells($lines[2]))->toBe(['', 'No', 'Not offered'])
            ->and($cells($lines[3]))->toBe(['', '', '']);
    });

    test('filters by developer roles and visa sponsorship', function () {
        $team = Team::factory()->create();
        careersSponsor(['team_id' => $team->id, 'name' => 'Offered Ltd', 'mentions_developer_roles' => true, 'visa_sponsorship' => VisaSponsorship::Offered]);
        careersSponsor(['team_id' => $team->id, 'name' => 'Refused Ltd', 'mentions_developer_roles' => true, 'visa_sponsorship' => VisaSponsorship::NotOffered]);
        careersSponsor(['team_id' => $team->id, 'name' => 'Quiet Ltd', 'mentions_developer_roles' => false]);

        $names = fn (array $options): array => array_column(array_slice(exportCareersRows($team, $options), 1), 0);

        expect($names(['--developer-roles' => true]))->toBe(['Offered Ltd', 'Refused Ltd'])
            ->and($names(['--visa-sponsorship' => 'offered']))->toBe(['Offered Ltd'])
            ->and($names(['--visa-sponsorship' => 'not_offered', '--developer-roles' => true]))->toBe(['Refused Ltd']);
    });

    test('fails on an unknown visa sponsorship value', function () {
        $team = Team::factory()->create();

        $this->artisan('sponsors:export', ['--team' => $team->slug, '--visa-sponsorship' => 'unknown'])
            ->expectsOutput('The --visa-sponsorship option must be offered or not_offered.')
            ->assertFailed();
    });
});

test('no test request leaves the fake', function () {
    fakeSite([CAREERS_SITE => homepageWith('<a href="/careers">Careers</a>'), CAREERS_SITE.'/careers' => CAREERS_PAGE_TEXT]);

    checkCareers(careersSponsor());

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), CAREERS_SITE));
    expect(Http::recorded()->every(fn (array $pair) => str_starts_with($pair[0]->url(), CAREERS_SITE)))->toBeTrue();
});

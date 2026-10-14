<?php

declare(strict_types=1);

use App\Enums\AiSource;
use App\Enums\SponsorStatus;
use App\Enums\TeamRole;
use App\Enums\TechReason;
use App\Jobs\EnrichSponsor;
use App\Models\Sponsor;
use App\Models\Team;
use App\Models\User;
use App\Services\HostResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

const AI_HOMEPAGE = 'https://www.acmesoftware.co.uk';

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config([
        'sponsor-finder.companies_house_key' => 'ch-key',
        'sponsor-finder.brave_key' => 'brave-key',
    ]);
    app()->instance(HostResolver::class, new class extends HostResolver
    {
        public function addresses(string $host): array
        {
            return ['93.184.215.14'];
        }
    });
});

/**
 * Fake Companies House (one exact match for the name), Brave (the homepage as the only result),
 * and the homepage itself.
 */
function fakeAiApis(string $title, string $homepage, bool $withResult = true): void
{
    Http::fake([
        'https://api.company-information.service.gov.uk/search/companies*' => Http::response(['items' => [
            ['title' => $title, 'company_number' => '01234567', 'company_status' => 'active'],
        ]]),
        'https://api.company-information.service.gov.uk/company/*' => Http::response([
            'company_name' => $title,
            'company_number' => '01234567',
            'company_status' => 'active',
            'sic_codes' => ['62012'],
        ]),
        'https://api.search.brave.com/*' => Http::response(['web' => ['results' => $withResult
            ? [['url' => AI_HOMEPAGE.'/about', 'title' => 'About Acme Software']]
            : []]]),
        AI_HOMEPAGE => Http::response($homepage),
    ]);
}

function enrichForAi(string $name): Sponsor
{
    $sponsor = Sponsor::factory()->create(['name' => $name, 'town' => 'Sheffield']);

    EnrichSponsor::dispatch($sponsor);

    return $sponsor->refresh();
}

function aiTeamUser(Team $team): User
{
    $user = User::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    return $user;
}

test('the migration adds the AI fields and rolls back', function () {
    $sponsor = Sponsor::factory()->create()->refresh();

    expect($sponsor->is_ai)->toBeFalse()
        ->and($sponsor->ai_source)->toBeNull();

    $migration = require database_path('migrations/2026_10_14_000004_add_ai_fields_to_sponsors_table.php');

    $migration->down();
    expect(Schema::hasColumn('sponsors', 'is_ai'))->toBeFalse()
        ->and(Schema::hasColumn('sponsors', 'ai_source'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('sponsors', 'is_ai'))->toBeTrue()
        ->and(Schema::hasColumn('sponsors', 'ai_source'))->toBeTrue();
});

describe('the enrich job', function () {
    test('tags a sponsor from its name', function () {
        fakeAiApis('ACME AI LIMITED', '<p>Acme AI builds tools.</p>');

        $sponsor = enrichForAi('Acme AI Ltd');

        expect($sponsor->status)->toBe(SponsorStatus::Done)
            ->and($sponsor->is_ai)->toBeTrue()
            ->and($sponsor->ai_source)->toBe(AiSource::Name);
    });

    test('tags a sponsor from its homepage text', function () {
        fakeAiApis('ACME SOFTWARE LIMITED', '<p>Acme Software builds machine learning tools.</p>');

        $sponsor = enrichForAi('Acme Software Ltd');

        expect($sponsor->status)->toBe(SponsorStatus::Done)
            ->and($sponsor->is_ai)->toBeTrue()
            ->and($sponsor->ai_source)->toBe(AiSource::Homepage);
    });

    test('keeps the name as the source when the homepage also matches', function () {
        fakeAiApis('ACME AI LIMITED', '<p>Acme AI builds machine learning tools.</p>');

        expect(enrichForAi('Acme AI Ltd')->ai_source)->toBe(AiSource::Name);
    });

    test('leaves the tag off when neither the name nor the homepage matches', function () {
        fakeAiApis('ACME SOFTWARE LIMITED', '<p>Acme Software builds apps. Email us.</p>');

        $sponsor = enrichForAi('Acme Software Ltd');

        expect($sponsor->status)->toBe(SponsorStatus::Done)
            ->and($sponsor->is_ai)->toBeFalse()
            ->and($sponsor->ai_source)->toBeNull();
    });

    test('leaves the tag off when no website is found', function () {
        fakeAiApis('ACME SOFTWARE LIMITED', '', withResult: false);

        $sponsor = enrichForAi('Acme Software Ltd');

        expect($sponsor->website)->toBeNull()
            ->and($sponsor->is_ai)->toBeFalse();
    });

    test('tags a skipped sponsor from its name', function () {
        Http::fake([
            'https://api.company-information.service.gov.uk/search/companies*' => Http::response(['items' => [
                ['title' => 'ACME ROBOTICS CARE LIMITED', 'company_number' => '01234567'],
            ]]),
            'https://api.company-information.service.gov.uk/company/*' => Http::response([
                'company_number' => '01234567',
                'company_status' => 'active',
                'sic_codes' => ['87100'],
            ]),
        ]);

        $sponsor = enrichForAi('Acme Robotics Care Ltd');

        expect($sponsor->status)->toBe(SponsorStatus::Skipped)
            ->and($sponsor->is_ai)->toBeTrue()
            ->and($sponsor->ai_source)->toBe(AiSource::Name);
    });

    test('checks the homepage with no extra request', function () {
        fakeAiApis('ACME SOFTWARE LIMITED', '<p>Acme Software builds machine learning tools.</p>');

        enrichForAi('Acme Software Ltd');

        // Companies House search and profile, one search, one homepage fetch.
        Http::assertSentCount(4);
        expect(Http::recorded(fn ($request) => str_starts_with($request->url(), AI_HOMEPAGE))->count())->toBe(1);
    });
});

test('shared lookups copy the AI tag', function () {
    Sponsor::factory()->for(Team::factory()->create(['share_lookups' => true]))->create([
        'name' => 'Acme Software Ltd',
        'town' => 'Sheffield',
        'status' => SponsorStatus::Done,
        'company_status' => 'active',
        'is_tech' => true,
        'tech_reason' => TechReason::Sic,
        'website' => AI_HOMEPAGE,
        'confidence' => 80,
        'is_ai' => true,
        'ai_source' => AiSource::Homepage,
    ]);

    $target = Sponsor::factory()->for(Team::factory()->create(['share_lookups' => true]))->create([
        'name' => 'Acme Software Ltd',
        'town' => 'Sheffield',
    ]);

    EnrichSponsor::dispatch($target);
    $target->refresh();

    Http::assertNothingSent();

    expect($target->is_ai)->toBeTrue()
        ->and($target->ai_source)->toBe(AiSource::Homepage);
});

test('the dashboard shows the tag and filters AI sponsors', function () {
    $team = Team::factory()->create();
    $ai = Sponsor::factory()->for($team)->create(['name' => 'Acme AI Ltd', 'is_ai' => true, 'ai_source' => AiSource::Name]);
    Sponsor::factory()->for($team)->create(['name' => 'Acme Software Ltd']);

    $this->actingAs(aiTeamUser($team))
        ->get(route('sponsors.index', ['current_team' => $team->slug, 'ai' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sponsors/index')
            ->where('filters.ai', true)
            ->has('sponsors.data', 1)
            ->where('sponsors.data.0.id', $ai->id)
            ->where('sponsors.data.0.is_ai', true));
});

test('the dashboard rejects a non-boolean AI filter', function () {
    $team = Team::factory()->create();

    $this->actingAs(aiTeamUser($team))
        ->get(route('sponsors.index', ['current_team' => $team->slug, 'ai' => 'maybe']))
        ->assertSessionHasErrors('ai');
});

test('the export has an AI column', function () {
    $team = Team::factory()->create();
    $done = ['status' => SponsorStatus::Done, 'website' => 'https://example.test'];
    Sponsor::factory()->for($team)->create(['name' => 'Acme AI Ltd', 'confidence' => 90, 'is_ai' => true, ...$done]);
    Sponsor::factory()->for($team)->create(['name' => 'Acme Software Ltd', 'confidence' => 80, ...$done]);

    $this->artisan('sponsors:export', ['--team' => $team->slug])->assertSuccessful();

    $path = storage_path('app/exports/'.$team->slug.'_tech_sponsors_'.now()->format('Y-m-d').'.csv');
    $lines = array_map(str_getcsv(...), file($path, FILE_IGNORE_NEW_LINES) ?: []);
    @unlink($path);

    $column = array_search('AI', $lines[0], true);

    expect($column)->not->toBeFalse()
        ->and([$lines[1][0], $lines[1][$column]])->toBe(['Acme AI Ltd', 'Yes'])
        ->and([$lines[2][0], $lines[2][$column]])->toBe(['Acme Software Ltd', 'No']);
});

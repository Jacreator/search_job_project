<?php

declare(strict_types=1);

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Enums\TeamRole;
use App\Enums\TechReason;
use App\Jobs\EnrichSponsor;
use App\Models\Sponsor;
use App\Models\Team;
use App\Models\User;
use App\Services\SharedLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config([
        'sponsor-finder.companies_house_key' => 'ch-key',
        'sponsor-finder.brave_key' => 'brave-key',
    ]);
});

function sharingTeam(bool $share = true): Team
{
    return Team::factory()->create(['share_lookups' => $share]);
}

/**
 * A finished lookup in another team, ready to be copied.
 *
 * @param  array<string, mixed>  $attributes
 */
function sharedSource(Team $team, array $attributes = []): Sponsor
{
    return Sponsor::factory()->for($team)->create([
        'name' => 'ACME SOFTWARE LIMITED',
        'town' => 'SHEFFIELD',
        'status' => SponsorStatus::Done,
        'company_number' => '01234567',
        'company_status' => 'active',
        'sic_codes' => ['62012'],
        'is_tech' => true,
        'tech_reason' => TechReason::Sic,
        'website' => 'https://www.acmesoftware.co.uk',
        'confidence' => 80,
        'region' => 'Elsewhere',
        'priority' => 1,
        'rating_grade' => 'A',
        'rating_grade_manual' => true,
        ...$attributes,
    ]);
}

/**
 * A sponsor waiting for its lookup.
 *
 * @param  array<string, mixed>  $attributes
 */
function sharedTarget(Team $team, array $attributes = []): Sponsor
{
    return Sponsor::factory()->for($team)->create([
        'name' => 'Acme Software Ltd',
        'town' => 'Sheffield',
        'region' => 'Yorkshire',
        'priority' => 100,
        'rating_grade' => null,
        'rating_grade_manual' => false,
        ...$attributes,
    ]);
}

function runEnrich(Sponsor $sponsor): Sponsor
{
    EnrichSponsor::dispatch($sponsor);

    return $sponsor->refresh();
}

function sharingTeamUser(Team $team, TeamRole $role): User
{
    $user = User::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);

    return $user;
}

function flaggedSponsor(Team $team): Sponsor
{
    return Sponsor::factory()->for($team)->create([
        'status' => SponsorStatus::Done,
        'website' => 'https://www.acmesoftware.co.uk',
        'confidence' => 74,
        'needs_second_check' => true,
    ]);
}

test('the migrations add both flags, off by default, and roll back', function () {
    $team = Team::factory()->create();
    $sponsor = Sponsor::factory()->for($team)->create();

    expect($team->refresh()->share_lookups)->toBeFalse()
        ->and($sponsor->refresh()->needs_second_check)->toBeFalse();

    foreach ([
        ['2026_10_14_000001_add_share_lookups_to_teams_table.php', 'teams', 'share_lookups'],
        ['2026_10_14_000002_add_needs_second_check_to_sponsors_table.php', 'sponsors', 'needs_second_check'],
    ] as [$file, $table, $column]) {
        $migration = require database_path("migrations/{$file}");

        $migration->down();
        expect(Schema::hasColumn($table, $column))->toBeFalse();

        $migration->up();
        expect(Schema::hasColumn($table, $column))->toBeTrue();
    }
});

describe('the toggle', function () {
    test('owners and admins can turn lookup sharing on and off', function (TeamRole $role) {
        $team = Team::factory()->create();
        $actor = sharingTeamUser($team, $role);

        $this->actingAs($actor)
            ->patch(route('teams.lookup-sharing', $team), ['share_lookups' => true])
            ->assertRedirect(route('teams.edit', $team))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Lookups are now shared with other teams.']);

        expect($team->refresh()->share_lookups)->toBeTrue();

        $this->actingAs($actor)
            ->patch(route('teams.lookup-sharing', $team), ['share_lookups' => false])
            ->assertRedirect(route('teams.edit', $team))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Lookups are no longer shared.']);

        expect($team->refresh()->share_lookups)->toBeFalse();
    })->with([
        'owner' => TeamRole::Owner,
        'admin' => TeamRole::Admin,
    ]);

    test('a member cannot change lookup sharing', function () {
        $team = Team::factory()->create();

        $this->actingAs(sharingTeamUser($team, TeamRole::Member))
            ->patch(route('teams.lookup-sharing', $team), ['share_lookups' => true])
            ->assertForbidden();

        expect($team->refresh()->share_lookups)->toBeFalse();
    });

    test('a user from another team cannot change lookup sharing', function () {
        $team = Team::factory()->create();
        $outsider = sharingTeamUser(Team::factory()->create(), TeamRole::Owner);

        $this->actingAs($outsider)
            ->patch(route('teams.lookup-sharing', $team), ['share_lookups' => true])
            ->assertForbidden();

        expect($team->refresh()->share_lookups)->toBeFalse();
    });

    test('a guest is sent to the login page', function () {
        $team = Team::factory()->create();

        $this->patch(route('teams.lookup-sharing', $team), ['share_lookups' => true])
            ->assertRedirect(route('login'));
    });

    test('the setting must be a boolean', function (mixed $value) {
        $team = Team::factory()->create();

        $this->actingAs(sharingTeamUser($team, TeamRole::Owner))
            ->patch(route('teams.lookup-sharing', $team), ['share_lookups' => $value])
            ->assertSessionHasErrors('share_lookups');

        expect($team->refresh()->share_lookups)->toBeFalse();
    })->with([
        'missing' => null,
        'text' => 'yes please',
        'number' => 5,
    ]);

    test('the team page shows the setting', function () {
        $team = sharingTeam();

        $this->actingAs(sharingTeamUser($team, TeamRole::Member))
            ->get(route('teams.edit', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams/edit')
                ->where('team.shareLookups', true));
    });
});

describe('the enrich job', function () {
    test('copies the result when both teams share, with no API calls', function () {
        $source = sharedSource(sharingTeam());
        $target = runEnrich(sharedTarget(sharingTeam()));

        Http::assertNothingSent();

        expect($target->status)->toBe(SponsorStatus::Done)
            ->and($target->company_number)->toBe('01234567')
            ->and($target->company_status)->toBe('active')
            ->and($target->sic_codes)->toBe(['62012'])
            ->and($target->is_tech)->toBeTrue()
            ->and($target->tech_reason)->toBe(TechReason::Sic)
            ->and($target->skip_reason)->toBeNull()
            ->and($target->website)->toBe($source->website)
            ->and($target->confidence)->toBe(80)
            ->and($target->needs_second_check)->toBeFalse()
            ->and($target->attempts)->toBe(1);
    });

    test('copies skipped results with their skip and tech reasons', function () {
        sharedSource(sharingTeam(), [
            'status' => SponsorStatus::Skipped,
            'skip_reason' => SkipReason::NotTech,
            'is_tech' => false,
            'tech_reason' => null,
            'website' => null,
            'confidence' => null,
        ]);

        $target = runEnrich(sharedTarget(sharingTeam()));

        Http::assertNothingSent();

        expect($target->status)->toBe(SponsorStatus::Skipped)
            ->and($target->skip_reason)->toBe(SkipReason::NotTech)
            ->and($target->is_tech)->toBeFalse()
            ->and($target->tech_reason)->toBeNull();
    });

    test('copies a keyword tech reason', function () {
        sharedSource(sharingTeam(), ['tech_reason' => TechReason::Keyword, 'sic_codes' => []]);

        expect(runEnrich(sharedTarget(sharingTeam()))->tech_reason)->toBe(TechReason::Keyword);
    });

    test('runs the normal pipeline when either team does not share', function (bool $sourceShares, bool $targetShares) {
        sharedSource(sharingTeam($sourceShares));

        Http::fake([
            'https://api.company-information.service.gov.uk/search/companies*' => Http::response(['items' => []]),
        ]);

        $target = runEnrich(sharedTarget(sharingTeam($targetShares)));

        Http::assertSentCount(1);

        expect($target->status)->toBe(SponsorStatus::Skipped)
            ->and($target->skip_reason)->toBe(SkipReason::NoMatch)
            ->and($target->company_number)->toBeNull();
    })->with([
        'source off' => [false, true],
        'target off' => [true, false],
        'both off' => [false, false],
    ]);

    test('never copies confirmed, region, priority, or rating', function () {
        sharedSource(sharingTeam(), ['confirmed' => false]);
        sharedSource(sharingTeam(), ['confirmed' => true, 'confidence' => 100]);

        $target = runEnrich(sharedTarget(sharingTeam()));

        expect($target->confirmed)->toBeFalse()
            ->and($target->region)->toBe('Yorkshire')
            ->and($target->priority)->toBe(100)
            ->and($target->rating_grade)->toBeNull()
            ->and($target->rating_grade_manual)->toBeFalse();
    });

    test('caps a copy of a confirmed website at 74 and flags it for a second check', function (int $sourceConfidence, int $copied) {
        sharedSource(sharingTeam(), ['confirmed' => true, 'confidence' => $sourceConfidence]);

        $target = runEnrich(sharedTarget(sharingTeam()));

        expect($target->confidence)->toBe($copied)
            ->and($target->needs_second_check)->toBeTrue()
            ->and($target->confirmed)->toBeFalse();
    })->with([
        'hand-saved 100' => [100, 74],
        'already below the cap' => [60, 60],
    ]);

    test('keeps the confidence of an unconfirmed source and does not flag it', function () {
        sharedSource(sharingTeam(), ['confirmed' => false, 'confidence' => 95]);

        $target = runEnrich(sharedTarget(sharingTeam()));

        expect($target->confidence)->toBe(95)
            ->and($target->needs_second_check)->toBeFalse();
    });

    test('never uses a source skipped with a B rating', function () {
        sharedSource(sharingTeam(), ['status' => SponsorStatus::Skipped, 'skip_reason' => SkipReason::BRating]);

        expect(app(SharedLookup::class)->find(sharedTarget(sharingTeam())))->toBeNull();
    });

    test('never uses a source that is unfinished or failed', function (SponsorStatus $status) {
        sharedSource(sharingTeam(), ['status' => $status]);

        expect(app(SharedLookup::class)->find(sharedTarget(sharingTeam())))->toBeNull();
    })->with([
        'pending' => SponsorStatus::Pending,
        'ch_done' => SponsorStatus::ChDone,
        'failed' => SponsorStatus::Failed,
    ]);

    test('copies a ch_done row too, before the web search', function () {
        sharedSource(sharingTeam());

        $target = runEnrich(sharedTarget(sharingTeam(), [
            'status' => SponsorStatus::ChDone,
            'company_status' => 'active',
            'is_tech' => true,
        ]));

        Http::assertNothingSent();

        expect($target->status)->toBe(SponsorStatus::Done)
            ->and($target->website)->toBe('https://www.acmesoftware.co.uk');
    });

    test('running twice on a copied row changes nothing', function () {
        sharedSource(sharingTeam());
        $target = runEnrich(sharedTarget(sharingTeam()));
        $before = $target->getAttributes();

        expect(runEnrich($target)->getAttributes())->toBe($before);
        Http::assertNothingSent();
    });
});

describe('finding a source', function () {
    test('returns null when the sponsor\'s team does not share', function () {
        sharedSource(sharingTeam());

        expect(app(SharedLookup::class)->find(sharedTarget(sharingTeam(false))))->toBeNull();
    });

    test('matches the normalised name and the town ignoring case', function () {
        $source = sharedSource(sharingTeam(), ['name' => 'The Acme Software Group Ltd.', 'town' => 'sheffield']);

        expect(app(SharedLookup::class)->find(sharedTarget(sharingTeam()))?->id)->toBe($source->id);
    });

    test('does not match another town or another name', function (array $attributes) {
        sharedSource(sharingTeam(), $attributes);

        expect(app(SharedLookup::class)->find(sharedTarget(sharingTeam())))->toBeNull();
    })->with([
        'other town' => [['town' => 'Leeds']],
        'other name' => [['name' => 'Acme Software Solutions Ltd']],
    ]);

    test('never uses a row from the same team', function () {
        $team = sharingTeam();
        sharedSource($team, ['town' => 'Sheffield', 'name' => 'Acme Software Limited']);

        expect(app(SharedLookup::class)->find(sharedTarget($team)))->toBeNull();
    });

    test('never uses a row from a deleted team', function () {
        $team = sharingTeam();
        sharedSource($team);
        $team->delete();

        expect(app(SharedLookup::class)->find(sharedTarget(sharingTeam())))->toBeNull();
    });

    test('prefers confirmed, then the highest confidence, then the most recently updated', function () {
        $target = sharedTarget(sharingTeam());
        $lookup = app(SharedLookup::class);

        $older = sharedSource(sharingTeam(), ['confidence' => 70, 'updated_at' => now()->subDay()]);
        $newer = sharedSource(sharingTeam(), ['confidence' => 70]);
        expect($lookup->find($target)?->id)->toBe($newer->id);

        $higher = sharedSource(sharingTeam(), ['confidence' => 90, 'updated_at' => now()->subWeek()]);
        expect($lookup->find($target)?->id)->toBe($higher->id);

        $confirmed = sharedSource(sharingTeam(), ['confidence' => 55, 'confirmed' => true, 'updated_at' => now()->subMonth()]);
        expect($lookup->find($target)?->id)->toBe($confirmed->id);

        expect($older->id)->not->toBe($lookup->find($target)?->id);
    });
});

describe('the stored normalised name', function () {
    test('the migration fills it for existing rows and rolls back', function () {
        $sponsor = Sponsor::factory()->create(['name' => 'The Acme Software Group Ltd.']);
        DB::table('sponsors')->where('id', $sponsor->id)->update(['normalised_name' => '']);

        $migration = require database_path('migrations/2026_10_14_000003_add_normalised_name_to_sponsors_table.php');

        $migration->down();
        expect(Schema::hasColumn('sponsors', 'normalised_name'))->toBeFalse();

        $migration->up();
        expect(Schema::hasColumn('sponsors', 'normalised_name'))->toBeTrue()
            ->and(DB::table('sponsors')->where('id', $sponsor->id)->value('normalised_name'))->toBe('acme software');
    });

    test('the model sets it on create and when the name changes', function () {
        $sponsor = Sponsor::factory()->create(['name' => 'Acme Software Ltd']);
        expect($sponsor->refresh()->normalised_name)->toBe('acme software');

        $sponsor->update(['name' => 'Bolt Digital Limited']);
        expect($sponsor->refresh()->normalised_name)->toBe('bolt digital');
    });

    test('the lookup matches on the stored column', function () {
        $source = sharedSource(sharingTeam());
        DB::table('sponsors')->where('id', $source->id)->update(['normalised_name' => 'something else']);

        expect(app(SharedLookup::class)->find(sharedTarget(sharingTeam())))->toBeNull();
    });
});

describe('the second check', function () {
    test('confirming the row clears the flag', function () {
        $team = Team::factory()->create();
        $sponsor = flaggedSponsor($team);

        $this->actingAs(sharingTeamUser($team, TeamRole::Owner))
            ->patch(route('sponsors.update', ['current_team' => $team->slug, 'sponsor' => $sponsor]), ['confirmed' => true])
            ->assertSessionHasNoErrors();

        expect($sponsor->refresh()->needs_second_check)->toBeFalse()
            ->and($sponsor->confirmed)->toBeTrue();
    });

    test('saving a website by hand clears the flag and sets confidence 100', function () {
        $team = Team::factory()->create();
        $sponsor = flaggedSponsor($team);

        $this->actingAs(sharingTeamUser($team, TeamRole::Owner))
            ->patch(route('sponsors.update', ['current_team' => $team->slug, 'sponsor' => $sponsor]), ['website' => 'https://acme.example'])
            ->assertSessionHasNoErrors();

        expect($sponsor->refresh()->needs_second_check)->toBeFalse()
            ->and($sponsor->confidence)->toBe(100);
    });

    test('unconfirming keeps the flag', function () {
        $team = Team::factory()->create();
        $sponsor = flaggedSponsor($team);

        $this->actingAs(sharingTeamUser($team, TeamRole::Owner))
            ->patch(route('sponsors.update', ['current_team' => $team->slug, 'sponsor' => $sponsor]), ['confirmed' => false])
            ->assertSessionHasNoErrors();

        expect($sponsor->refresh()->needs_second_check)->toBeTrue();
    });

    test('the dashboard shows the flag and filters by it', function () {
        $team = Team::factory()->create();
        $flagged = flaggedSponsor($team);
        Sponsor::factory()->for($team)->create(['status' => SponsorStatus::Done]);

        $this->actingAs(sharingTeamUser($team, TeamRole::Member))
            ->get(route('sponsors.index', ['current_team' => $team->slug, 'needs_second_check' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sponsors/index')
                ->where('filters.needs_second_check', true)
                ->has('sponsors.data', 1)
                ->where('sponsors.data.0.id', $flagged->id)
                ->where('sponsors.data.0.needs_second_check', true));
    });

    test('the dashboard rejects a non-boolean filter', function () {
        $team = Team::factory()->create();

        $this->actingAs(sharingTeamUser($team, TeamRole::Member))
            ->get(route('sponsors.index', ['current_team' => $team->slug, 'needs_second_check' => 'maybe']))
            ->assertSessionHasErrors('needs_second_check');
    });

    test('the export has a needs second check column', function () {
        $team = Team::factory()->create();
        $flagged = flaggedSponsor($team);
        Sponsor::factory()->for($team)->create([
            'name' => 'Other Ltd',
            'status' => SponsorStatus::Done,
            'website' => 'https://other.example',
            'confidence' => 90,
        ]);

        $this->artisan('sponsors:export', ['--team' => $team->slug])->assertSuccessful();

        $path = storage_path('app/exports/'.$team->slug.'_tech_sponsors_'.now()->format('Y-m-d').'.csv');
        $lines = array_map(str_getcsv(...), file($path, FILE_IGNORE_NEW_LINES) ?: []);
        @unlink($path);

        $column = array_search('Needs second check', $lines[0], true);

        expect($column)->not->toBeFalse()
            ->and($lines[1][0])->toBe('Other Ltd')
            ->and($lines[1][$column])->toBe('No')
            ->and($lines[2][0])->toBe($flagged->name)
            ->and($lines[2][$column])->toBe('Yes');
    });

    test('sponsors:status counts flagged rows', function () {
        $team = Team::factory()->create();
        flaggedSponsor($team);
        flaggedSponsor($team);
        Sponsor::factory()->for($team)->create();

        $this->artisan('sponsors:status', ['--team' => $team->slug])
            ->expectsTable(['Needs second check', 'Count'], [['yes', 2]])
            ->assertSuccessful();
    });
});

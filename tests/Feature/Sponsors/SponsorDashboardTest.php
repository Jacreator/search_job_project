<?php

declare(strict_types=1);

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Enums\TeamRole;
use App\Models\Sponsor;
use App\Models\Team;
use App\Models\User;
use App\Services\SponsorDashboard;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['sponsor-finder.skip_b_rated' => true]);
});

function dashboardUser(Team $team, TeamRole $role = TeamRole::Member, bool $canEditSponsors = false): User
{
    $user = User::factory()->create();

    $team->members()->attach($user, ['role' => $role->value, 'can_edit_sponsors' => $canEditSponsors]);

    return $user;
}

/**
 * A finished sponsor with a website, which the dashboard lets editors change.
 *
 * @param  array<string, mixed>  $attributes
 */
function doneSponsor(Team $team, array $attributes = []): Sponsor
{
    return Sponsor::factory()->for($team)->create([
        'status' => SponsorStatus::Done,
        'website' => 'https://example.test',
        'confidence' => 60,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $query
 */
function listSponsors(User $user, Team $team, array $query = []): TestResponse
{
    return test()->actingAs($user)->get(route('sponsors.index', ['current_team' => $team->slug, ...$query]));
}

/**
 * Names on the page for the given filters, in page order.
 *
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function listedNames(User $user, Team $team, array $query = []): array
{
    return array_column(listSponsors($user, $team, $query)->assertOk()->inertiaProps('sponsors.data'), 'name');
}

/**
 * @param  array<string, mixed>  $data
 */
function updateSponsor(User $user, Team $team, Sponsor $sponsor, array $data): TestResponse
{
    return test()->actingAs($user)
        ->from(route('sponsors.index', ['current_team' => $team->slug, 'status' => 'done']))
        ->patch(route('sponsors.update', ['current_team' => $team->slug, 'sponsor' => $sponsor->id]), $data);
}

function setRating(User $user, Team $team, Sponsor $sponsor, mixed $grade): TestResponse
{
    return test()->actingAs($user)
        ->from(route('sponsors.index', ['current_team' => $team->slug]))
        ->patch(route('sponsors.rating.update', ['current_team' => $team->slug, 'sponsor' => $sponsor->id]), ['grade' => $grade]);
}

// Listing

test('guests are redirected to login', function () {
    $team = Team::factory()->create();
    $sponsor = doneSponsor($team);

    $this->get(route('sponsors.index', ['current_team' => $team->slug]))->assertRedirect(route('login'));
    $this->patch(route('sponsors.update', ['current_team' => $team->slug, 'sponsor' => $sponsor->id]), ['confirmed' => true])
        ->assertRedirect(route('login'));
    $this->patch(route('sponsors.rating.update', ['current_team' => $team->slug, 'sponsor' => $sponsor->id]), ['grade' => 'A'])
        ->assertRedirect(route('login'));

    expect($sponsor->fresh()->confirmed)->toBeFalse();
});

test('a member sees the team\'s sponsors', function () {
    $team = Team::factory()->create(['name' => 'Acme Team']);
    $member = dashboardUser($team);
    $sponsor = doneSponsor($team, [
        'name' => 'Acme Software Ltd',
        'town' => 'Sheffield',
        'region' => 'Yorkshire',
        'sic_codes' => ['62012', '62020'],
        'website' => 'https://acme.test',
        'confidence' => 85,
        'confirmed' => true,
        'route' => 'Skilled Worker, Scale-up',
    ]);

    listSponsors($member, $team)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sponsors/index')
            ->where('team', ['name' => 'Acme Team', 'slug' => $team->slug])
            ->where('sponsors.total', 1)
            ->where('sponsors.per_page', 50)
            ->where('sponsors.data.0', [
                'id' => $sponsor->id,
                'name' => 'Acme Software Ltd',
                'town' => 'Sheffield',
                'region' => 'Yorkshire',
                'rating_grade' => 'A',
                'rating_grade_manual' => false,
                'sic_codes' => ['62012', '62020'],
                'website' => 'https://acme.test',
                'confidence' => 85,
                'confirmed' => true,
                'needs_second_check' => false,
                'status' => 'done',
                'skip_reason' => null,
                'is_scale_up' => true,
                'is_ai' => false,
            ])
        );
});

test('only the current team\'s sponsors are listed and counted', function () {
    $team = Team::factory()->create();
    $other = Team::factory()->create();
    $member = dashboardUser($team);
    doneSponsor($team, ['name' => 'Ours', 'region' => 'Yorkshire']);
    doneSponsor($other, ['name' => 'Theirs', 'region' => 'London']);

    listSponsors($member, $team)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sponsors.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Ours'])
            ->where('statusCounts.2', ['value' => 'done', 'count' => 1])
            ->where('regions', ['Yorkshire'])
        );
});

test('a user cannot see another team\'s sponsors', function () {
    $team = Team::factory()->create();
    doneSponsor($team);
    $outsider = dashboardUser(Team::factory()->create(), TeamRole::Owner);

    listSponsors($outsider, $team)->assertForbidden();
});

test('rows with the highest confidence come first, then priority, then name', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    Sponsor::factory()->for($team)->create(['name' => 'Pending Low', 'priority' => 0]);
    Sponsor::factory()->for($team)->create(['name' => 'Pending High B', 'priority' => 100]);
    Sponsor::factory()->for($team)->create(['name' => 'Pending High A', 'priority' => 100]);
    doneSponsor($team, ['name' => 'Done 60', 'confidence' => 60]);
    doneSponsor($team, ['name' => 'Done 90', 'confidence' => 90]);

    expect(listedNames($member, $team))->toBe(['Done 90', 'Done 60', 'Pending High A', 'Pending High B', 'Pending Low']);
});

test('the table shows 50 rows per page and keeps filters in the page links', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    Sponsor::factory()->for($team)->count(SponsorDashboard::PER_PAGE + 1)->create(['town' => 'Leeds']);

    listSponsors($member, $team, ['town' => 'Leeds'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sponsors.data', 50)
            ->where('sponsors.total', 51)
            ->where('sponsors.last_page', 2)
            ->where('sponsors.prev_page_url', null)
            ->where('sponsors.next_page_url', fn (string $url) => str_contains($url, 'town=Leeds') && str_contains($url, 'page=2'))
        );

    listSponsors($member, $team, ['town' => 'Leeds', 'page' => 2])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('sponsors.data', 1)->where('sponsors.current_page', 2));
});

test('a Scale-up sponsor is flagged, alone or with other routes', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    doneSponsor($team, ['name' => 'A Alone', 'route' => 'Scale-up']);
    doneSponsor($team, ['name' => 'B Joined', 'route' => 'Skilled Worker, Scale-up']);
    doneSponsor($team, ['name' => 'C Skilled', 'route' => 'Skilled Worker']);

    expect(array_column(listSponsors($member, $team)->inertiaProps('sponsors.data'), 'is_scale_up'))->toBe([true, true, false]);
});

// Summary counts

test('status counts and skip counts by reason are shown', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    Sponsor::factory()->for($team)->count(3)->create();
    Sponsor::factory()->for($team)->create(['status' => SponsorStatus::ChDone]);
    doneSponsor($team);
    Sponsor::factory()->for($team)->create(['status' => SponsorStatus::Failed]);

    foreach ([SkipReason::NotTech, SkipReason::NotTech, SkipReason::NoMatch, SkipReason::BRating] as $reason) {
        Sponsor::factory()->for($team)->create(['status' => SponsorStatus::Skipped, 'skip_reason' => $reason]);
    }

    // A filter does not change the summary.
    listSponsors($member, $team, ['status' => 'done'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sponsors.total', 1)
            ->where('statusCounts', [
                ['value' => 'pending', 'count' => 3],
                ['value' => 'ch_done', 'count' => 1],
                ['value' => 'done', 'count' => 1],
                ['value' => 'skipped', 'count' => 4],
                ['value' => 'failed', 'count' => 1],
            ])
            ->where('skipReasonCounts', [
                ['value' => 'no_match', 'count' => 1],
                ['value' => 'not_tech', 'count' => 2],
                ['value' => 'inactive', 'count' => 0],
                ['value' => 'b_rating', 'count' => 1],
            ])
        );
});

// Filters

test('filters by status', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    doneSponsor($team, ['name' => 'Finished']);
    Sponsor::factory()->for($team)->create(['name' => 'Waiting']);

    expect(listedNames($member, $team, ['status' => 'pending']))->toBe(['Waiting']);
});

test('filters by town and region, ignoring case', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    doneSponsor($team, ['name' => 'Sheffield Co', 'town' => 'Sheffield', 'region' => 'Yorkshire']);
    doneSponsor($team, ['name' => 'Leeds Co', 'town' => 'Leeds', 'region' => 'Yorkshire']);
    doneSponsor($team, ['name' => 'London Co', 'town' => 'London', 'region' => 'London']);
    doneSponsor($team, ['name' => 'Nowhere Co', 'town' => '', 'region' => null]);

    expect(listedNames($member, $team, ['town' => 'sheffield']))->toBe(['Sheffield Co'])
        ->and(listedNames($member, $team, ['region' => 'YORKSHIRE']))->toBe(['Leeds Co', 'Sheffield Co']);
});

test('filters by minimum confidence', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    doneSponsor($team, ['name' => 'Eighty', 'confidence' => 80]);
    doneSponsor($team, ['name' => 'Seventy Five', 'confidence' => 75]);
    doneSponsor($team, ['name' => 'Forty', 'confidence' => 40]);
    Sponsor::factory()->for($team)->create(['name' => 'Unscored']);

    expect(listedNames($member, $team, ['min_confidence' => 75]))->toBe(['Eighty', 'Seventy Five']);
});

test('filters by tech only', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    doneSponsor($team, ['name' => 'Tech', 'is_tech' => true]);
    doneSponsor($team, ['name' => 'Not Tech', 'is_tech' => false]);
    doneSponsor($team, ['name' => 'Unchecked', 'is_tech' => null]);

    expect(listedNames($member, $team, ['tech' => 1]))->toBe(['Tech']);
});

test('filters by Scale-up only', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    doneSponsor($team, ['name' => 'Scale Alone', 'route' => 'Scale-up']);
    doneSponsor($team, ['name' => 'Scale Joined', 'route' => 'Skilled Worker, Scale-up']);
    doneSponsor($team, ['name' => 'Skilled Only', 'route' => 'Skilled Worker']);

    expect(listedNames($member, $team, ['scale_up' => 1]))->toBe(['Scale Alone', 'Scale Joined']);
});

test('filters by rating missing', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    doneSponsor($team, ['name' => 'Graded A']);
    doneSponsor($team, ['name' => 'Graded B', 'rating_grade' => 'B']);
    doneSponsor($team, ['name' => 'Ungraded', 'rating_grade' => null]);

    expect(listedNames($member, $team, ['rating_missing' => 1]))->toBe(['Ungraded']);
});

test('searches by part of the name, ignoring case and treating wildcards as text', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    doneSponsor($team, ['name' => 'Acme Software Ltd']);
    doneSponsor($team, ['name' => 'Bolt Hardware Ltd']);
    doneSponsor($team, ['name' => '100% Digital']);

    expect(listedNames($member, $team, ['search' => 'SOFT']))->toBe(['Acme Software Ltd'])
        ->and(listedNames($member, $team, ['search' => '%']))->toBe(['100% Digital'])
        ->and(listedNames($member, $team, ['search' => '_']))->toBe([]);
});

test('combines filters and returns them to the page', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    doneSponsor($team, ['name' => 'Match Ltd', 'town' => 'Sheffield', 'region' => 'Yorkshire', 'is_tech' => true, 'confidence' => 90]);
    doneSponsor($team, ['name' => 'Match Low Ltd', 'town' => 'Sheffield', 'region' => 'Yorkshire', 'is_tech' => true, 'confidence' => 40]);
    doneSponsor($team, ['name' => 'Match Leeds Ltd', 'town' => 'Leeds', 'region' => 'Yorkshire', 'is_tech' => true, 'confidence' => 90]);

    listSponsors($member, $team, [
        'status' => 'done', 'town' => ' Sheffield ', 'region' => 'Yorkshire', 'min_confidence' => 50, 'tech' => 1, 'search' => 'match',
    ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sponsors.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Match Ltd'])
            ->where('filters', [
                'status' => 'done',
                'town' => 'Sheffield',
                'region' => 'Yorkshire',
                'min_confidence' => 50,
                'tech' => true,
                'scale_up' => false,
                'rating_missing' => false,
                'needs_second_check' => false,
                'ai' => false,
                'search' => 'match',
            ])
        );
});

test('invalid filters fail validation', function (string $key, mixed $value) {
    $team = Team::factory()->create();
    $member = dashboardUser($team);

    listSponsors($member, $team, [$key => $value])->assertSessionHasErrors($key);
})->with([
    'unknown status' => ['status', 'nope'],
    'confidence over 100' => ['min_confidence', 101],
    'confidence not a number' => ['min_confidence', 'high'],
    'tech not a boolean' => ['tech', 'maybe'],
]);

// Permissions shown on the page

test('the page says who may edit sponsors and set ratings', function (TeamRole $role, bool $flag, bool $canEdit, bool $canSetRatings) {
    $team = Team::factory()->create();
    $user = dashboardUser($team, $role, $flag);

    listSponsors($user, $team)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions', ['canEdit' => $canEdit, 'canSetRatings' => $canSetRatings])
        );
})->with([
    'owner' => [TeamRole::Owner, false, true, true],
    'admin' => [TeamRole::Admin, false, true, true],
    'member with the edit flag' => [TeamRole::Member, true, true, false],
    'member without the edit flag' => [TeamRole::Member, false, false, false],
]);

// Updating the website and confirmed flag

test('owners, admins, and flagged members can save a website by hand', function (TeamRole $role, bool $flag) {
    $team = Team::factory()->create();
    $user = dashboardUser($team, $role, $flag);
    $sponsor = doneSponsor($team, ['website' => 'https://wrong.test', 'confidence' => 30]);

    updateSponsor($user, $team, $sponsor, ['website' => 'https://right.test'])
        ->assertRedirect(route('sponsors.index', ['current_team' => $team->slug, 'status' => 'done']))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Sponsor updated.']);

    expect($sponsor->fresh())
        ->website->toBe('https://right.test')
        ->confidence->toBe(100)
        ->confirmed->toBeTrue()
        ->status->toBe(SponsorStatus::Done);
})->with([
    'owner' => [TeamRole::Owner, false],
    'admin' => [TeamRole::Admin, false],
    'member with the edit flag' => [TeamRole::Member, true],
]);

test('a member without the edit flag can view but not update', function () {
    $team = Team::factory()->create();
    $member = dashboardUser($team);
    $sponsor = doneSponsor($team, ['website' => 'https://kept.test']);

    listSponsors($member, $team)->assertOk();

    updateSponsor($member, $team, $sponsor, ['website' => 'https://changed.test'])->assertForbidden();
    updateSponsor($member, $team, $sponsor, ['confirmed' => true])->assertForbidden();

    expect($sponsor->fresh())->website->toBe('https://kept.test')->confirmed->toBeFalse();
});

test('a website can be added to a finished sponsor that has none', function () {
    $team = Team::factory()->create();
    $owner = dashboardUser($team, TeamRole::Owner);
    $sponsor = doneSponsor($team, ['website' => null, 'confidence' => null]);

    updateSponsor($owner, $team, $sponsor, ['website' => 'http://found.test/about'])->assertSessionHasNoErrors();

    expect($sponsor->fresh())->website->toBe('http://found.test/about')->confidence->toBe(100)->confirmed->toBeTrue();
});

test('confirming and unconfirming leaves the website and confidence alone', function () {
    $team = Team::factory()->create();
    $owner = dashboardUser($team, TeamRole::Owner);
    $sponsor = doneSponsor($team, ['website' => 'https://acme.test', 'confidence' => 80]);

    updateSponsor($owner, $team, $sponsor, ['confirmed' => true])->assertSessionHasNoErrors();
    expect($sponsor->fresh())->confirmed->toBeTrue()->confidence->toBe(80)->website->toBe('https://acme.test');

    updateSponsor($owner, $team, $sponsor, ['confirmed' => false])->assertSessionHasNoErrors();
    expect($sponsor->fresh())->confirmed->toBeFalse()->confidence->toBe(80);
});

test('the website must be an http or https address', function (mixed $website) {
    $team = Team::factory()->create();
    $owner = dashboardUser($team, TeamRole::Owner);
    $sponsor = doneSponsor($team, ['website' => 'https://kept.test', 'confidence' => 60]);

    updateSponsor($owner, $team, $sponsor, ['website' => $website])->assertSessionHasErrors('website');

    expect($sponsor->fresh())->website->toBe('https://kept.test')->confidence->toBe(60)->confirmed->toBeFalse();
})->with([
    'no scheme' => ['acme.test'],
    'ftp' => ['ftp://acme.test'],
    'javascript' => ['javascript:alert(1)'],
    'empty' => [''],
    'too long' => ['https://acme.test/'.str_repeat('a', 250)],
]);

test('an update needs a website or a confirmed value', function () {
    $team = Team::factory()->create();
    $owner = dashboardUser($team, TeamRole::Owner);
    $sponsor = doneSponsor($team);

    updateSponsor($owner, $team, $sponsor, [])->assertSessionHasErrors(['website', 'confirmed']);
    updateSponsor($owner, $team, $sponsor, ['confirmed' => 'maybe'])->assertSessionHasErrors('confirmed');
});

test('a sponsor without a website cannot be confirmed', function () {
    $team = Team::factory()->create();
    $owner = dashboardUser($team, TeamRole::Owner);
    $sponsor = doneSponsor($team, ['website' => null, 'confidence' => null]);

    updateSponsor($owner, $team, $sponsor, ['confirmed' => true])
        ->assertSessionHasErrors(['confirmed' => 'Add a website before confirming it.']);

    expect($sponsor->fresh()->confirmed)->toBeFalse();
});

test('only sponsors with a finished lookup can be edited', function (SponsorStatus $status) {
    $team = Team::factory()->create();
    $owner = dashboardUser($team, TeamRole::Owner);
    $sponsor = Sponsor::factory()->for($team)->create(['status' => $status]);

    updateSponsor($owner, $team, $sponsor, ['website' => 'https://acme.test'])
        ->assertSessionHasErrors(['website' => 'Only sponsors with a finished lookup can be edited.']);

    expect($sponsor->fresh())->website->toBeNull()->confirmed->toBeFalse();
})->with([
    'pending' => SponsorStatus::Pending,
    'ch_done' => SponsorStatus::ChDone,
    'skipped' => SponsorStatus::Skipped,
    'failed' => SponsorStatus::Failed,
]);

test('a user cannot update another team\'s sponsor', function () {
    $team = Team::factory()->create();
    $other = Team::factory()->create();
    $owner = dashboardUser($team, TeamRole::Owner);
    $theirs = doneSponsor($other, ['website' => 'https://theirs.test']);

    // Through their own team's URL the sponsor does not exist. Through the other team's URL they are not a member.
    updateSponsor($owner, $team, $theirs, ['website' => 'https://mine.test'])->assertNotFound();
    updateSponsor($owner, $other, $theirs, ['website' => 'https://mine.test'])->assertForbidden();
    setRating($owner, $team, $theirs, 'B')->assertNotFound();
    setRating($owner, $other, $theirs, 'B')->assertForbidden();

    expect($theirs->fresh())->website->toBe('https://theirs.test')->rating_grade->toBe('A');
});

// Setting a missing rating

test('owners and admins can set a missing grade to A, which leaves the status alone', function (TeamRole $role) {
    $team = Team::factory()->create();
    $user = dashboardUser($team, $role);
    $sponsor = doneSponsor($team, ['rating_grade' => null]);

    setRating($user, $team, $sponsor, 'A')
        ->assertRedirect(route('sponsors.index', ['current_team' => $team->slug]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Rating set to A.']);

    expect($sponsor->fresh())
        ->rating_grade->toBe('A')
        ->rating_grade_manual->toBeTrue()
        ->status->toBe(SponsorStatus::Done)
        ->skip_reason->toBeNull();
})->with([
    'owner' => TeamRole::Owner,
    'admin' => TeamRole::Admin,
]);

test('owners and admins can set a missing grade to B, which skips the row', function (TeamRole $role) {
    $team = Team::factory()->create();
    $user = dashboardUser($team, $role);
    $sponsor = doneSponsor($team, ['rating_grade' => null, 'website' => 'https://kept.test', 'confirmed' => true]);

    setRating($user, $team, $sponsor, 'B')
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Rating set to B. The sponsor is now skipped.']);

    expect($sponsor->fresh())
        ->rating_grade->toBe('B')
        ->rating_grade_manual->toBeTrue()
        ->status->toBe(SponsorStatus::Skipped)
        ->skip_reason->toBe(SkipReason::BRating)
        ->website->toBe('https://kept.test')
        ->confirmed->toBeTrue();
})->with([
    'owner' => TeamRole::Owner,
    'admin' => TeamRole::Admin,
]);

test('changing a hand-set B to A returns the row to pending', function () {
    $team = Team::factory()->create();
    $owner = dashboardUser($team, TeamRole::Owner);
    $sponsor = Sponsor::factory()->for($team)->create([
        'rating_grade' => 'B',
        'rating_grade_manual' => true,
        'status' => SponsorStatus::Skipped,
        'skip_reason' => SkipReason::BRating,
    ]);

    setRating($owner, $team, $sponsor, 'A')
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Rating set to A. The sponsor is pending again.']);

    expect($sponsor->fresh())
        ->rating_grade->toBe('A')
        ->rating_grade_manual->toBeTrue()
        ->status->toBe(SponsorStatus::Pending)
        ->skip_reason->toBeNull();
});

test('owners and admins can change a hand-set grade', function (TeamRole $role) {
    $team = Team::factory()->create();
    $user = dashboardUser($team, $role);
    $sponsor = doneSponsor($team, ['rating_grade' => 'A', 'rating_grade_manual' => true]);

    setRating($user, $team, $sponsor, 'B')->assertSessionHasNoErrors();

    expect($sponsor->fresh())->rating_grade->toBe('B')->status->toBe(SponsorStatus::Skipped);
})->with([
    'owner' => TeamRole::Owner,
    'admin' => TeamRole::Admin,
]);

test('a grade from the register cannot be changed', function (TeamRole $role, string $grade) {
    $team = Team::factory()->create();
    $user = dashboardUser($team, $role);
    $sponsor = doneSponsor($team, ['rating_grade' => $grade, 'rating_grade_manual' => false]);

    setRating($user, $team, $sponsor, $grade === 'A' ? 'B' : 'A')->assertForbidden();

    expect($sponsor->fresh())->rating_grade->toBe($grade)->rating_grade_manual->toBeFalse()->status->toBe(SponsorStatus::Done);
})->with([
    'owner, A' => [TeamRole::Owner, 'A'],
    'admin, B' => [TeamRole::Admin, 'B'],
]);

test('a member cannot set a grade, with or without the edit flag', function (bool $flag) {
    $team = Team::factory()->create();
    $member = dashboardUser($team, TeamRole::Member, $flag);
    $sponsor = doneSponsor($team, ['rating_grade' => null]);

    setRating($member, $team, $sponsor, 'A')->assertForbidden();

    expect($sponsor->fresh())->rating_grade->toBeNull()->rating_grade_manual->toBeFalse();
})->with([
    'with the edit flag' => true,
    'without the edit flag' => false,
]);

test('a grade that is not A or B fails validation', function (mixed $grade) {
    $team = Team::factory()->create();
    $owner = dashboardUser($team, TeamRole::Owner);
    $sponsor = doneSponsor($team, ['rating_grade' => null]);

    setRating($owner, $team, $sponsor, $grade)->assertSessionHasErrors('grade');

    expect($sponsor->fresh())->rating_grade->toBeNull()->rating_grade_manual->toBeFalse();
})->with([
    'C' => ['C'],
    'lower case' => ['a'],
    'empty' => [''],
    'missing' => [null],
]);

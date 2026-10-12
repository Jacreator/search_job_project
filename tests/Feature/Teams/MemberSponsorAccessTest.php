<?php

declare(strict_types=1);

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

function teamUser(Team $team, TeamRole $role, bool $canEditSponsors = false): User
{
    $user = User::factory()->create();

    $team->members()->attach($user, ['role' => $role->value, 'can_edit_sponsors' => $canEditSponsors]);

    return $user;
}

function sponsorAccessOf(Team $team, User $user): bool
{
    return Membership::query()->where('team_id', $team->id)->where('user_id', $user->id)->firstOrFail()->can_edit_sponsors;
}

function setSponsorAccess(User $actor, Team $team, User $target, mixed $canEdit): TestResponse
{
    return test()
        ->actingAs($actor)
        ->patch(route('teams.members.sponsor-access', [$team, $target]), ['can_edit_sponsors' => $canEdit]);
}

test('the migration adds the flag, off by default, and rolls back', function () {
    $team = Team::factory()->create();
    $member = teamUser($team, TeamRole::Member);
    $team->members()->detach($member);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect(Schema::hasColumn('team_members', 'can_edit_sponsors'))->toBeTrue()
        ->and(sponsorAccessOf($team, $member))->toBeFalse();

    $migration = require database_path('migrations/2026_10_12_000001_add_can_edit_sponsors_to_team_members_table.php');

    $migration->down();
    expect(Schema::hasColumn('team_members', 'can_edit_sponsors'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('team_members', 'can_edit_sponsors'))->toBeTrue();
});

test('owners and admins have the manage sponsor editors permission and members do not', function () {
    expect(TeamPermission::ManageSponsorEditors->value)->toBe('sponsor:manage-editors')
        ->and(TeamRole::Owner->hasPermission(TeamPermission::ManageSponsorEditors))->toBeTrue()
        ->and(TeamRole::Admin->hasPermission(TeamPermission::ManageSponsorEditors))->toBeTrue()
        ->and(TeamRole::Member->hasPermission(TeamPermission::ManageSponsorEditors))->toBeFalse();
});

test('owners and admins can turn sponsor editing on and off for a member', function (TeamRole $role) {
    $team = Team::factory()->create();
    $actor = teamUser($team, $role);
    $member = teamUser($team, TeamRole::Member);

    setSponsorAccess($actor, $team, $member, true)
        ->assertRedirect(route('teams.edit', $team))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Member can now edit sponsors.']);

    expect(sponsorAccessOf($team, $member))->toBeTrue()
        ->and($member->canEditSponsors($team))->toBeTrue();

    setSponsorAccess($actor, $team, $member, false)
        ->assertRedirect(route('teams.edit', $team))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Member can no longer edit sponsors.']);

    expect(sponsorAccessOf($team, $member))->toBeFalse()
        ->and($member->canEditSponsors($team))->toBeFalse();
})->with([
    'owner' => TeamRole::Owner,
    'admin' => TeamRole::Admin,
]);

test('a member cannot change sponsor editing for anyone', function () {
    $team = Team::factory()->create();
    $actor = teamUser($team, TeamRole::Member, canEditSponsors: true);
    $member = teamUser($team, TeamRole::Member);

    setSponsorAccess($actor, $team, $member, true)->assertForbidden();
    setSponsorAccess($actor, $team, $actor, false)->assertForbidden();

    expect(sponsorAccessOf($team, $member))->toBeFalse()
        ->and(sponsorAccessOf($team, $actor))->toBeTrue();
});

test('sponsor editing cannot be changed for an owner or admin', function (TeamRole $role) {
    $team = Team::factory()->create();
    $owner = teamUser($team, TeamRole::Owner);
    $target = $role === TeamRole::Owner ? $owner : teamUser($team, $role);

    setSponsorAccess($owner, $team, $target, true)
        ->assertSessionHasErrors(['can_edit_sponsors' => 'Owners and admins can always edit sponsors.']);

    expect(sponsorAccessOf($team, $target))->toBeFalse();
})->with([
    'owner' => TeamRole::Owner,
    'admin' => TeamRole::Admin,
]);

test('a user from another team cannot change sponsor editing', function (TeamRole $role) {
    $team = Team::factory()->create();
    $member = teamUser($team, TeamRole::Member);
    $outsider = teamUser(Team::factory()->create(), $role);

    setSponsorAccess($outsider, $team, $member, true)->assertForbidden();

    expect(sponsorAccessOf($team, $member))->toBeFalse();
})->with([
    'owner' => TeamRole::Owner,
    'admin' => TeamRole::Admin,
]);

test('sponsor editing cannot be changed for someone outside the team', function () {
    $team = Team::factory()->create();
    $owner = teamUser($team, TeamRole::Owner);
    $otherTeam = Team::factory()->create();
    $outsider = teamUser($otherTeam, TeamRole::Member);

    setSponsorAccess($owner, $team, $outsider, true)->assertNotFound();

    expect(sponsorAccessOf($otherTeam, $outsider))->toBeFalse();
});

test('the sponsor editing value must be a boolean', function (mixed $value) {
    $team = Team::factory()->create();
    $owner = teamUser($team, TeamRole::Owner);
    $member = teamUser($team, TeamRole::Member);

    setSponsorAccess($owner, $team, $member, $value)->assertSessionHasErrors('can_edit_sponsors');

    expect(sponsorAccessOf($team, $member))->toBeFalse();
})->with([
    'missing' => [null],
    'text' => ['yes'],
    'number' => [2],
]);

test('sponsor editing is cleared when a member becomes an admin', function () {
    $team = Team::factory()->create();
    $owner = teamUser($team, TeamRole::Owner);
    $member = teamUser($team, TeamRole::Member, canEditSponsors: true);

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), ['role' => TeamRole::Admin->value])
        ->assertRedirect(route('teams.edit', $team));

    expect(sponsorAccessOf($team, $member))->toBeFalse()
        ->and($member->canEditSponsors($team))->toBeTrue();

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), ['role' => TeamRole::Member->value])
        ->assertRedirect(route('teams.edit', $team));

    expect(sponsorAccessOf($team, $member))->toBeFalse()
        ->and($member->canEditSponsors($team))->toBeFalse();
});

test('sponsor editing is kept when a member stays a member', function () {
    $team = Team::factory()->create();
    $owner = teamUser($team, TeamRole::Owner);
    $member = teamUser($team, TeamRole::Member, canEditSponsors: true);

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), ['role' => TeamRole::Member->value])
        ->assertRedirect(route('teams.edit', $team));

    expect(sponsorAccessOf($team, $member))->toBeTrue();
});

test('guests cannot change sponsor editing', function () {
    $team = Team::factory()->create();
    $member = teamUser($team, TeamRole::Member);

    $this->patch(route('teams.members.sponsor-access', [$team, $member]), ['can_edit_sponsors' => true])
        ->assertRedirect(route('login'));

    expect(sponsorAccessOf($team, $member))->toBeFalse();
});

test('the team edit page shows each member\'s sponsor editing and who may manage it', function (TeamRole $role, bool $canManage) {
    $team = Team::factory()->create();
    $viewer = teamUser($team, $role);
    $editor = teamUser($team, TeamRole::Member, canEditSponsors: true);

    $this->actingAs($viewer)
        ->get(route('teams.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/edit')
            ->where('permissions.canManageSponsorEditors', $canManage)
            ->where('members', fn ($members) => collect($members)->pluck('can_edit_sponsors', 'id')->all() === [
                $viewer->id => false,
                $editor->id => true,
            ])
        );
})->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
]);

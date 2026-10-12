<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

/**
 * A user with the given role in the team.
 */
function sponsorTeamUser(Team $team, TeamRole $role, bool $canEditSponsors = false): User
{
    $user = User::factory()->create();

    $team->members()->attach($user, ['role' => $role->value, 'can_edit_sponsors' => $canEditSponsors]);

    return $user;
}

it('lets an owner edit sponsors', function () {
    $team = Team::factory()->create();

    expect(sponsorTeamUser($team, TeamRole::Owner)->canEditSponsors($team))->toBeTrue();
});

it('lets an admin edit sponsors', function () {
    $team = Team::factory()->create();

    expect(sponsorTeamUser($team, TeamRole::Admin)->canEditSponsors($team))->toBeTrue();
});

it('lets a member edit sponsors when their flag is on', function () {
    $team = Team::factory()->create();

    expect(sponsorTeamUser($team, TeamRole::Member, canEditSponsors: true)->canEditSponsors($team))->toBeTrue();
});

it('does not let a member edit sponsors when their flag is off', function () {
    $team = Team::factory()->create();

    expect(sponsorTeamUser($team, TeamRole::Member)->canEditSponsors($team))->toBeFalse();
});

it('does not let a non-member edit sponsors', function () {
    $team = Team::factory()->create();

    expect(User::factory()->create()->canEditSponsors($team))->toBeFalse();
});

it('checks the flag per team', function () {
    $team = Team::factory()->create();
    $other = Team::factory()->create();
    $user = sponsorTeamUser($team, TeamRole::Member, canEditSponsors: true);
    $other->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect($user->canEditSponsors($team))->toBeTrue()
        ->and($user->canEditSponsors($other))->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Models\Team;

function statusRows(int $pending = 0, int $chDone = 0, int $done = 0, int $skipped = 0, int $failed = 0): array
{
    return [
        ['pending', $pending],
        ['ch_done', $chDone],
        ['done', $done],
        ['skipped', $skipped],
        ['failed', $failed],
    ];
}

function techRows(int $yes = 0, int $no = 0, int $notChecked = 0): array
{
    return [['yes', $yes], ['no', $no], ['not checked', $notChecked]];
}

function skipRows(int $noMatch = 0, int $notTech = 0, int $inactive = 0, int $bRating = 0): array
{
    return [
        ['no_match', $noMatch],
        ['not_tech', $notTech],
        ['inactive', $inactive],
        ['b_rating', $bRating],
    ];
}

function skippedSponsor(Team $team, SkipReason $reason, ?bool $isTech = null): Sponsor
{
    return Sponsor::factory()->for($team)->create([
        'status' => SponsorStatus::Skipped,
        'skip_reason' => $reason,
        'is_tech' => $isTech,
    ]);
}

it('shows counts per status, tech result, and skip reason for one team', function () {
    $team = Team::factory()->create();
    Sponsor::factory()->for($team)->count(2)->create();
    Sponsor::factory()->for($team)->create(['status' => SponsorStatus::ChDone, 'is_tech' => true]);
    Sponsor::factory()->for($team)->create(['status' => SponsorStatus::Done, 'is_tech' => true]);
    Sponsor::factory()->for($team)->create(['status' => SponsorStatus::Failed, 'error' => 'Boom']);
    skippedSponsor($team, SkipReason::NoMatch);
    skippedSponsor($team, SkipReason::NotTech, false);
    skippedSponsor($team, SkipReason::NotTech, false);
    skippedSponsor($team, SkipReason::Inactive, true);
    skippedSponsor($team, SkipReason::BRating);

    // Another team's rows are left out.
    Sponsor::factory()->create(['status' => SponsorStatus::Done, 'is_tech' => true]);

    $this->artisan('sponsors:status', ['--team' => $team->slug])
        ->expectsOutput("Team: {$team->name} ({$team->slug})")
        ->expectsTable(['Status', 'Count'], statusRows(pending: 2, chDone: 1, done: 1, skipped: 5, failed: 1))
        ->expectsTable(['Tech', 'Count'], techRows(yes: 3, no: 2, notChecked: 5))
        ->expectsTable(['Skip reason', 'Count'], skipRows(noMatch: 1, notTech: 2, inactive: 1, bRating: 1))
        ->assertSuccessful();
});

it('shows counts across all teams without a team option', function () {
    Sponsor::factory()->create();
    Sponsor::factory()->create(['status' => SponsorStatus::Done, 'is_tech' => true]);
    skippedSponsor(Team::factory()->create(), SkipReason::NoMatch);

    $this->artisan('sponsors:status')
        ->expectsOutput('All teams')
        ->expectsTable(['Status', 'Count'], statusRows(pending: 1, done: 1, skipped: 1))
        ->expectsTable(['Tech', 'Count'], techRows(yes: 1, notChecked: 2))
        ->expectsTable(['Skip reason', 'Count'], skipRows(noMatch: 1))
        ->assertSuccessful();
});

it('shows zero counts for a team with no sponsors', function () {
    $team = Team::factory()->create();

    $this->artisan('sponsors:status', ['--team' => $team->slug])
        ->expectsTable(['Status', 'Count'], statusRows())
        ->expectsTable(['Tech', 'Count'], techRows())
        ->expectsTable(['Skip reason', 'Count'], skipRows())
        ->assertSuccessful();
});

it('lists skipped rows with no skip reason separately', function () {
    $team = Team::factory()->create();
    Sponsor::factory()->for($team)->create(['status' => SponsorStatus::Skipped, 'skip_reason' => null]);

    $this->artisan('sponsors:status', ['--team' => $team->slug])
        ->expectsTable(['Status', 'Count'], statusRows(skipped: 1))
        ->expectsTable(['Tech', 'Count'], techRows(notChecked: 1))
        ->expectsTable(['Skip reason', 'Count'], [...skipRows(), ['none', 1]])
        ->assertSuccessful();
});

it('fails for an unknown team', function () {
    $this->artisan('sponsors:status', ['--team' => 'no-such-team'])
        ->expectsOutput('Team [no-such-team] does not exist.')
        ->assertFailed();
});

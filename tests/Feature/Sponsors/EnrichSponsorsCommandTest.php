<?php

declare(strict_types=1);

use App\Enums\SponsorStatus;
use App\Jobs\EnrichSponsor;
use App\Models\Sponsor;
use App\Models\Team;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['sponsor-finder.batch_size' => 100]);
});

/**
 * Ids of the sponsors queued for lookup, in the order they were queued.
 *
 * @return list<int>
 */
function queuedSponsorIds(): array
{
    return Queue::pushed(EnrichSponsor::class)
        ->map(fn (EnrichSponsor $job) => $job->sponsor->id)
        ->values()
        ->all();
}

it('queues one job per sponsor ready for lookup', function () {
    $team = Team::factory()->create();
    $pending = Sponsor::factory()->for($team)->create();
    $chDone = Sponsor::factory()->for($team)->create(['status' => SponsorStatus::ChDone]);

    $this->artisan('sponsors:enrich')
        ->expectsOutput('Queued 2 sponsors for lookup.')
        ->assertSuccessful();

    expect(queuedSponsorIds())->toBe([$pending->id, $chDone->id]);
});

it('does not queue finished rows or rows out of attempts', function () {
    $team = Team::factory()->create();
    $ready = Sponsor::factory()->for($team)->create(['attempts' => Sponsor::MAX_ATTEMPTS - 1]);

    foreach ([SponsorStatus::Done, SponsorStatus::Skipped, SponsorStatus::Failed] as $status) {
        Sponsor::factory()->for($team)->create(['status' => $status]);
    }
    Sponsor::factory()->for($team)->create(['attempts' => Sponsor::MAX_ATTEMPTS]);

    $this->artisan('sponsors:enrich')
        ->expectsOutput('Queued 1 sponsor for lookup.')
        ->assertSuccessful();

    expect(queuedSponsorIds())->toBe([$ready->id]);
});

it('queues higher priority rows first and breaks ties by id', function () {
    $team = Team::factory()->create();
    $low = Sponsor::factory()->for($team)->create(['priority' => 0]);
    $highFirst = Sponsor::factory()->for($team)->create(['priority' => 100]);
    $middle = Sponsor::factory()->for($team)->create(['priority' => 80]);
    $highSecond = Sponsor::factory()->for($team)->create(['priority' => 100]);

    $this->artisan('sponsors:enrich')->assertSuccessful();

    expect(queuedSponsorIds())->toBe([$highFirst->id, $highSecond->id, $middle->id, $low->id]);
});

it('queues no more than the configured batch size', function () {
    config(['sponsor-finder.batch_size' => 2]);
    $team = Team::factory()->create();
    $sponsors = Sponsor::factory()->for($team)->count(3)->create();

    $this->artisan('sponsors:enrich')
        ->expectsOutput('Queued 2 sponsors for lookup.')
        ->assertSuccessful();

    expect(queuedSponsorIds())->toBe($sponsors->take(2)->pluck('id')->all());
});

it('applies the limit across all teams', function () {
    $first = Sponsor::factory()->create(['priority' => 100]);
    $second = Sponsor::factory()->create(['priority' => 90]);
    Sponsor::factory()->create(['priority' => 80]);

    $this->artisan('sponsors:enrich', ['--limit' => 2])
        ->expectsOutput('Queued 2 sponsors for lookup.')
        ->assertSuccessful();

    expect(queuedSponsorIds())->toBe([$first->id, $second->id])
        ->and($first->team_id)->not->toBe($second->team_id);
});

it('filters by team', function () {
    $team = Team::factory()->create();
    $ours = Sponsor::factory()->for($team)->create();
    Sponsor::factory()->create(['priority' => 100]);

    $this->artisan('sponsors:enrich', ['--team' => $team->slug])
        ->expectsOutput('Queued 1 sponsor for lookup.')
        ->assertSuccessful();

    expect(queuedSponsorIds())->toBe([$ours->id]);
});

it('filters by region, ignoring case', function () {
    $team = Team::factory()->create();
    $leeds = Sponsor::factory()->for($team)->create(['town' => 'Leeds', 'region' => 'Yorkshire', 'priority' => 80]);
    Sponsor::factory()->for($team)->create(['town' => 'Manchester', 'region' => 'North West', 'priority' => 60]);
    Sponsor::factory()->for($team)->create(['town' => 'Exeter', 'region' => null]);

    $this->artisan('sponsors:enrich', ['--region' => 'yorkshire'])
        ->expectsOutput('Queued 1 sponsor for lookup.')
        ->assertSuccessful();

    expect(queuedSponsorIds())->toBe([$leeds->id]);
});

it('filters by town, ignoring case', function () {
    $team = Team::factory()->create();
    $sheffield = Sponsor::factory()->for($team)->create(['town' => 'SHEFFIELD', 'region' => 'Yorkshire']);
    Sponsor::factory()->for($team)->create(['town' => 'Leeds', 'region' => 'Yorkshire']);

    $this->artisan('sponsors:enrich', ['--town' => 'Sheffield'])
        ->expectsOutput('Queued 1 sponsor for lookup.')
        ->assertSuccessful();

    expect(queuedSponsorIds())->toBe([$sheffield->id]);
});

it('combines the team, region, and town filters', function () {
    $team = Team::factory()->create();
    $match = Sponsor::factory()->for($team)->create(['town' => 'Sheffield', 'region' => 'Yorkshire']);
    Sponsor::factory()->for($team)->create(['town' => 'Leeds', 'region' => 'Yorkshire']);
    Sponsor::factory()->create(['town' => 'Sheffield', 'region' => 'Yorkshire']);

    $this->artisan('sponsors:enrich', ['--team' => $team->slug, '--region' => 'Yorkshire', '--town' => 'Sheffield'])
        ->expectsOutput('Queued 1 sponsor for lookup.')
        ->assertSuccessful();

    expect(queuedSponsorIds())->toBe([$match->id]);
});

it('leaves out sponsors whose lookup is still queued from an earlier run', function () {
    $team = Team::factory()->create();
    $first = Sponsor::factory()->for($team)->count(2)->create(['priority' => 100]);

    $this->artisan('sponsors:enrich')->expectsOutput('Queued 2 sponsors for lookup.')->assertSuccessful();

    $new = Sponsor::factory()->for($team)->create();

    $this->artisan('sponsors:enrich')
        ->expectsOutput('Queued 1 sponsor for lookup.')
        ->expectsOutput('Left out 2 sponsors already queued.')
        ->assertSuccessful();

    expect(queuedSponsorIds())->toBe([...$first->pluck('id')->all(), $new->id]);
});

it('queues nothing when no rows are ready', function () {
    Sponsor::factory()->create(['status' => SponsorStatus::Done]);

    $this->artisan('sponsors:enrich')
        ->expectsOutput('Queued 0 sponsors for lookup.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('fails for an unknown team', function () {
    Sponsor::factory()->create();

    $this->artisan('sponsors:enrich', ['--team' => 'no-such-team'])
        ->expectsOutput('Team [no-such-team] does not exist.')
        ->assertFailed();

    Queue::assertNothingPushed();
});

it('fails for a limit that is not a positive whole number', function (string $limit) {
    Sponsor::factory()->create();

    $this->artisan('sponsors:enrich', ['--limit' => $limit])
        ->expectsOutput('The --limit option must be a whole number above 0.')
        ->assertFailed();

    Queue::assertNothingPushed();
})->with(['0', '-5', 'ten', '2.5']);

it('is scheduled hourly without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'sponsors:enrich'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

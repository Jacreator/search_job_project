<?php

declare(strict_types=1);

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Enums\TechReason;
use App\Models\Sponsor;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

it('runs and rolls back the sponsors migration', function () {
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_10_04_000001_create_sponsors_table.php');

    expect(Schema::hasTable('sponsors'))->toBeTrue();

    $migration->down();
    expect(Schema::hasTable('sponsors'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('sponsors'))->toBeTrue();
});

it('creates a valid sponsor from the factory', function () {
    $sponsor = Sponsor::factory()->create()->fresh();

    expect($sponsor->team)->toBeInstanceOf(Team::class)
        ->and($sponsor->name)->not->toBeEmpty()
        ->and($sponsor->status)->toBe(SponsorStatus::Pending)
        ->and($sponsor->priority)->toBe(0)
        ->and($sponsor->attempts)->toBe(0)
        ->and($sponsor->confirmed)->toBeFalse()
        ->and($sponsor->rating_grade_manual)->toBeFalse()
        ->and($sponsor->is_tech)->toBeNull()
        ->and($sponsor->tech_reason)->toBeNull()
        ->and($sponsor->skip_reason)->toBeNull();
});

it('belongs to a team that has many sponsors', function () {
    $team = Team::factory()->create();
    Sponsor::factory()->count(2)->for($team)->create();
    Sponsor::factory()->create();

    expect($team->sponsors)->toHaveCount(2)
        ->each(fn ($sponsor) => $sponsor->team_id->toBe($team->id));
});

it('deletes sponsors when their team is deleted', function () {
    $sponsor = Sponsor::factory()->create();

    $sponsor->team->forceDelete();

    expect(Sponsor::query()->find($sponsor->id))->toBeNull();
});

it('allows the same name and town in two different teams', function () {
    Sponsor::factory()->create(['name' => 'Acme Software Ltd', 'town' => 'Sheffield']);
    Sponsor::factory()->create(['name' => 'Acme Software Ltd', 'town' => 'Sheffield']);

    expect(Sponsor::query()->where('name', 'Acme Software Ltd')->count())->toBe(2);
});

it('refuses the same name and town twice in one team', function () {
    $team = Team::factory()->create();
    Sponsor::factory()->for($team)->create(['name' => 'Acme Software Ltd', 'town' => 'Sheffield']);

    Sponsor::factory()->for($team)->create(['name' => 'Acme Software Ltd', 'town' => 'Sheffield']);
})->throws(UniqueConstraintViolationException::class);

it('refuses the same name twice in one team when the town is empty', function () {
    $team = Team::factory()->create();
    Sponsor::factory()->for($team)->create(['name' => 'Acme Software Ltd', 'town' => '']);

    Sponsor::factory()->for($team)->create(['name' => 'Acme Software Ltd', 'town' => '']);
})->throws(UniqueConstraintViolationException::class);

it('casts tech_reason and skip_reason to their enums', function () {
    $sponsor = Sponsor::factory()->create([
        'is_tech' => true,
        'tech_reason' => TechReason::KnownEmployer,
        'status' => SponsorStatus::Skipped,
        'skip_reason' => SkipReason::BRating,
    ])->fresh();

    expect($sponsor->tech_reason)->toBe(TechReason::KnownEmployer)
        ->and($sponsor->skip_reason)->toBe(SkipReason::BRating)
        ->and($sponsor->status)->toBe(SponsorStatus::Skipped)
        ->and($sponsor->getRawOriginal('tech_reason'))->toBe('known_employer')
        ->and($sponsor->getRawOriginal('skip_reason'))->toBe('b_rating');
});

it('casts sic_codes to an array and the flags to booleans', function () {
    $sponsor = Sponsor::factory()->create([
        'sic_codes' => ['62011', '62020'],
        'is_tech' => false,
        'confirmed' => true,
        'rating_grade_manual' => true,
    ])->fresh();

    expect($sponsor->sic_codes)->toBe(['62011', '62020'])
        ->and($sponsor->is_tech)->toBeFalse()
        ->and($sponsor->confirmed)->toBeTrue()
        ->and($sponsor->rating_grade_manual)->toBeTrue();
});

it('scopes to pending sponsors', function () {
    $pending = Sponsor::factory()->create();
    Sponsor::factory()->create(['status' => SponsorStatus::ChDone]);
    Sponsor::factory()->create(['status' => SponsorStatus::Done]);

    expect(Sponsor::query()->pending()->pluck('id')->all())->toBe([$pending->id]);
});

it('scopes to sponsors ready for lookup', function () {
    $pending = Sponsor::factory()->create(['attempts' => 0]);
    $chDone = Sponsor::factory()->create(['status' => SponsorStatus::ChDone, 'attempts' => 2]);
    Sponsor::factory()->create(['attempts' => 3]);
    Sponsor::factory()->create(['status' => SponsorStatus::ChDone, 'attempts' => 3]);
    Sponsor::factory()->create(['status' => SponsorStatus::Done]);
    Sponsor::factory()->create(['status' => SponsorStatus::Skipped, 'skip_reason' => SkipReason::NotTech]);
    Sponsor::factory()->create(['status' => SponsorStatus::Failed, 'attempts' => 1]);

    expect(Sponsor::query()->readyForLookup()->orderBy('id')->pluck('id')->all())
        ->toBe([$pending->id, $chDone->id]);
});

it('scopes to Scale-up sponsors, alone or with other routes', function () {
    $only = Sponsor::factory()->create(['route' => 'Scale-up']);
    $joined = Sponsor::factory()->create(['route' => 'Skilled Worker, Scale-up']);
    Sponsor::factory()->create(['route' => 'Skilled Worker']);
    Sponsor::factory()->create(['route' => 'Skilled Worker, Global Business Mobility: Senior or Specialist Worker']);

    expect(Sponsor::query()->scaleUp()->orderBy('id')->pluck('id')->all())->toBe([$only->id, $joined->id]);
});

it('combines the Scale-up scope with the A grade', function () {
    $aRated = Sponsor::factory()->create(['route' => 'Scale-up', 'rating_grade' => 'A']);
    Sponsor::factory()->create([
        'route' => 'Scale-up',
        'rating_grade' => 'B',
        'status' => SponsorStatus::Skipped,
        'skip_reason' => SkipReason::BRating,
    ]);

    expect(Sponsor::query()->scaleUp()->where('rating_grade', 'A')->pluck('id')->all())->toBe([$aRated->id]);
});

it('scopes to tech sponsors', function () {
    $tech = Sponsor::factory()->create(['is_tech' => true, 'tech_reason' => TechReason::Sic]);
    Sponsor::factory()->create(['is_tech' => false]);
    Sponsor::factory()->create(['is_tech' => null]);

    expect(Sponsor::query()->tech()->pluck('id')->all())->toBe([$tech->id]);
});

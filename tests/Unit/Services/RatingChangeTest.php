<?php

declare(strict_types=1);

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Services\RatingChange;
use Tests\TestCase;

uses(TestCase::class);

function sponsorWith(array $attributes): Sponsor
{
    return new Sponsor(array_merge(['status' => SponsorStatus::Pending], $attributes));
}

it('skips a row when A becomes B, whatever its status', function (SponsorStatus $status) {
    $sponsor = sponsorWith(['rating_grade' => 'A', 'status' => $status]);

    (new RatingChange(skipBRated: true))->apply($sponsor, 'B');

    expect($sponsor->rating_grade)->toBe('B')
        ->and($sponsor->status)->toBe(SponsorStatus::Skipped)
        ->and($sponsor->skip_reason)->toBe(SkipReason::BRating);
})->with([SponsorStatus::Pending, SponsorStatus::ChDone, SponsorStatus::Done, SponsorStatus::Failed]);

it('treats a missing grade becoming B like A to B', function () {
    $sponsor = sponsorWith(['rating_grade' => null, 'status' => SponsorStatus::Done]);

    (new RatingChange(skipBRated: true))->apply($sponsor, 'B');

    expect($sponsor->status)->toBe(SponsorStatus::Skipped)
        ->and($sponsor->skip_reason)->toBe(SkipReason::BRating);
});

it('returns a B-rating skip to pending when B becomes A', function () {
    $sponsor = sponsorWith([
        'rating_grade' => 'B',
        'status' => SponsorStatus::Skipped,
        'skip_reason' => SkipReason::BRating,
    ]);

    (new RatingChange(skipBRated: true))->apply($sponsor, 'A');

    expect($sponsor->rating_grade)->toBe('A')
        ->and($sponsor->status)->toBe(SponsorStatus::Pending)
        ->and($sponsor->skip_reason)->toBeNull();
});

it('leaves rows skipped for another reason alone', function (?string $from, string $to) {
    $sponsor = sponsorWith([
        'rating_grade' => $from,
        'status' => SponsorStatus::Skipped,
        'skip_reason' => SkipReason::NotTech,
    ]);

    (new RatingChange(skipBRated: true))->apply($sponsor, $to);

    expect($sponsor->rating_grade)->toBe($to)
        ->and($sponsor->status)->toBe(SponsorStatus::Skipped)
        ->and($sponsor->skip_reason)->toBe(SkipReason::NotTech);
})->with([
    'B to A' => ['B', 'A'],
    'A to B' => ['A', 'B'],
]);

it('changes only the grade when a missing grade becomes A', function () {
    $sponsor = sponsorWith(['rating_grade' => null, 'status' => SponsorStatus::Done]);

    (new RatingChange(skipBRated: true))->apply($sponsor, 'A');

    expect($sponsor->rating_grade)->toBe('A')
        ->and($sponsor->status)->toBe(SponsorStatus::Done)
        ->and($sponsor->skip_reason)->toBeNull();
});

it('changes nothing when the grade is the same', function () {
    $sponsor = sponsorWith(['rating_grade' => 'A', 'status' => SponsorStatus::Done]);
    $sponsor->syncOriginal();

    (new RatingChange(skipBRated: true))->apply($sponsor, 'A');

    expect($sponsor->isDirty())->toBeFalse();
});

it('updates only the grade when skip_b_rated is off', function () {
    $sponsor = sponsorWith(['rating_grade' => 'A', 'status' => SponsorStatus::Done]);

    (new RatingChange(skipBRated: false))->apply($sponsor, 'B');

    expect($sponsor->rating_grade)->toBe('B')
        ->and($sponsor->status)->toBe(SponsorStatus::Done)
        ->and($sponsor->skip_reason)->toBeNull();
});

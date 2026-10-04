<?php

declare(strict_types=1);

use App\Support\SponsorRating;

it('gives A for an A grade', function (string $value) {
    expect(SponsorRating::grade($value))->toBe('A');
})->with([
    'A rating' => 'A rating',
    'A (Premium)' => 'A (Premium)',
    'A (SME+)' => 'A (SME+)',
    'wrapped A rating' => 'Worker (A rating)',
    'wrapped A (Premium)' => 'Worker (A (Premium))',
    'temporary worker A (SME+)' => 'Temporary Worker (A (SME+))',
]);

it('gives B for a B grade', function (string $value) {
    expect(SponsorRating::grade($value))->toBe('B');
})->with([
    'B rating' => 'B rating',
    'wrapped B rating' => 'Worker (B rating)',
]);

it('gives null for UK Expansion Worker: Provisional', function (string $value) {
    expect(SponsorRating::grade($value))->toBeNull();
})->with([
    'bare' => 'UK Expansion Worker: Provisional',
    'as in the register' => 'Worker (UK Expansion Worker: Provisional )',
]);

it('gives null for any other value', function (string $value) {
    expect(SponsorRating::grade($value))->toBeNull();
})->with([
    'empty' => '',
    'unknown grade' => 'C rating',
    'grade letter only' => 'Worker (A)',
    'grade without wrapper text' => 'A',
    'other text' => 'Sponsor',
]);

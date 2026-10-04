<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('has the default batch size and rate', function () {
    expect(config('sponsor-finder.batch_size'))->toBe(100)
        ->and(config('sponsor-finder.rate_per_minute'))->toBe(30);
});

it('imports the four worker routes by default', function () {
    expect(config('sponsor-finder.routes'))->toBe([
        'Skilled Worker',
        'Global Business Mobility: Senior or Specialist Worker',
        'Global Business Mobility: Graduate Trainee',
        'Scale-up',
    ]);
});

it('skips B-rated sponsors by default', function () {
    expect(config('sponsor-finder.skip_b_rated'))->toBeTrue();
});

it('stores tech SIC codes as strings', function () {
    expect(config('sponsor-finder.tech_sic_codes'))
        ->toHaveCount(10)
        ->toContain('62011', '72190')
        ->each->toBeString();
});

it('has tech and noise keywords', function () {
    expect(config('sponsor-finder.tech_keywords'))->toContain('software', 'ai', 'developer')
        ->and(config('sponsor-finder.noise_keywords'))->toContain('care', 'recruitment', 'property');
});

it('has a starter list of known employers', function () {
    expect(config('sponsor-finder.known_employers'))
        ->not->toBeEmpty()
        ->toContain('Sky UK Limited');
});

it('gives Sheffield the highest priority', function () {
    $locations = config('sponsor-finder.priority_locations');

    expect($locations['Sheffield'])->toBe(['region' => 'Yorkshire', 'priority' => 100])
        ->and(max(array_column($locations, 'priority')))->toBe(100);
});

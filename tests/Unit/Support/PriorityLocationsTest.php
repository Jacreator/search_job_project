<?php

declare(strict_types=1);

use App\Support\PriorityLocations;
use Tests\TestCase;

uses(TestCase::class);

it('matches towns case-insensitively', function (string $town) {
    $location = app(PriorityLocations::class)->for($town);

    expect($location->region)->toBe('Yorkshire')
        ->and($location->priority)->toBe(100);
})->with(['sheffield', 'SHEFFIELD', 'Sheffield', ' Sheffield ']);

it('gives unlisted and empty towns no region and priority 0', function (?string $town) {
    $location = app(PriorityLocations::class)->for($town);

    expect($location->region)->toBeNull()
        ->and($location->priority)->toBe(0);
})->with(['Bristol', '', null]);

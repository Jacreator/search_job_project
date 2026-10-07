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

it('trims whitespace from the API keys', function () {
    $keys = ['COMPANIES_HOUSE_KEY' => "\tch-key \r", 'BRAVE_SEARCH_KEY' => '  brave-key'];
    $saved = [$_SERVER, $_ENV];

    try {
        foreach ($keys as $name => $value) {
            $_SERVER[$name] = $_ENV[$name] = $value;
        }

        $config = require config_path('sponsor-finder.php');
    } finally {
        [$_SERVER, $_ENV] = $saved;
    }

    expect($config['companies_house_key'])->toBe('ch-key')
        ->and($config['brave_key'])->toBe('brave-key');
});

it('uses the live Companies House API by default', function () {
    expect(config('sponsor-finder.companies_house_url'))->toBe('https://api.company-information.service.gov.uk');
});

it('reads the Companies House URL from the environment without a trailing slash', function () {
    $saved = [$_SERVER, $_ENV];

    try {
        $_SERVER['COMPANIES_HOUSE_URL'] = $_ENV['COMPANIES_HOUSE_URL'] = ' https://ch.example.test/ ';

        $config = require config_path('sponsor-finder.php');
    } finally {
        [$_SERVER, $_ENV] = $saved;
    }

    expect($config['companies_house_url'])->toBe('https://ch.example.test');
});

it('has no API keys when they are empty', function () {
    expect(config('sponsor-finder.companies_house_key'))->toBeNull()
        ->and(config('sponsor-finder.brave_key'))->toBeNull();
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
        ->toHaveCount(30)
        ->toContain('Sky UK Limited');
});

it('spells known employers as the register does', function () {
    expect(config('sponsor-finder.known_employers'))
        ->toContain(
            'BT Group', 'HSBC Holdings plc', 'NatWest Group PLC', 'Jet2.com', 'Asda Stores Ltd',
            'J Sainsbury Plc', 'Marks and Spencer Group Plc', 'Rightmove Group Ltd', 'Ernst & Young',
            'Tata Consultancy Services',
        )
        ->not->toContain(
            'British Telecommunications plc', 'HSBC UK Bank plc', 'National Westminster Bank plc',
            'Jet2.com Limited', 'Asda Stores Limited', "Sainsbury's Supermarkets Ltd", 'Marks and Spencer plc',
            'Rightmove plc', 'Ernst & Young LLP', 'Tata Consultancy Services Limited',
        );
});

it('gives Sheffield the highest priority', function () {
    $locations = config('sponsor-finder.priority_locations');

    expect($locations['Sheffield'])->toBe(['region' => 'Yorkshire', 'priority' => 100])
        ->and(max(array_column($locations, 'priority')))->toBe(100);
});

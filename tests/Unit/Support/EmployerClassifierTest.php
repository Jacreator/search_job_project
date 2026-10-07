<?php

declare(strict_types=1);

use App\Enums\TechReason;
use App\Support\EmployerClassifier;
use Tests\TestCase;

uses(TestCase::class);

function classifier(): EmployerClassifier
{
    return app(EmployerClassifier::class);
}

it('gives Sic for a tech SIC code', function () {
    expect(classifier()->classify('Acme Widgets Ltd', ['62012']))->toBe(TechReason::Sic);
});

it('gives Sic when one of several SIC codes is tech', function () {
    expect(classifier()->classify('Acme Widgets Ltd', ['47110', '62020', '86900']))->toBe(TechReason::Sic);
});

it('checks the SIC code before the name', function () {
    expect(classifier()->classify('Digital Care Ltd', ['62012']))->toBe(TechReason::Sic)
        ->and(classifier()->classify('Sky UK Limited', ['62012']))->toBe(TechReason::Sic);
});

it('gives Keyword for a tech word in the name when no SIC code is tech', function (string $name) {
    expect(classifier()->classify($name, ['47110']))->toBe(TechReason::Keyword);
})->with([
    'software' => 'Acme Software Ltd',
    'ai' => 'Acme AI Limited',
    'data' => 'Sheffield Data Services Ltd',
    'cyber, in capitals' => 'NORTHERN CYBER LTD',
    'apps' => 'Acme Apps Ltd',
    'after a trading name is cut' => 'Acme Technologies Ltd t/a Widget World',
]);

it('gives null for a tech word next to a noise word', function (string $name) {
    expect(classifier()->classify($name, []))->toBeNull();
})->with([
    'digital care' => 'Digital Care Ltd',
    'property developer' => 'Acme Property Developer Ltd',
    'tech recruitment' => 'Tech Recruitment Partners Ltd',
]);

it('gives KnownEmployer for a known employer', function () {
    expect(classifier()->classify('Sky UK Limited', []))->toBe(TechReason::KnownEmployer)
        ->and(classifier()->classify('Tesco Stores Limited', ['47110']))->toBe(TechReason::KnownEmployer);
});

it('matches known employers by their register spelling', function (string $name) {
    expect(classifier()->classify($name, []))->toBe(TechReason::KnownEmployer);
})->with(['BT Group', 'NatWest Group PLC', 'Jet2.com', 'J Sainsbury Plc', 'Ernst & Young', 'Tata Consultancy Services']);

it('does not match the old starter list spellings', function (string $name) {
    expect(classifier()->classify($name, []))->toBeNull();
})->with(['British Telecommunications plc', 'National Westminster Bank plc', "Sainsbury's Supermarkets Ltd", 'Rightmove plc']);

it('matches known employers ignoring case and extra spaces', function (string $name) {
    expect(classifier()->classify($name, []))->toBe(TechReason::KnownEmployer);
})->with([
    'capitals' => 'SKY UK LIMITED',
    'extra space' => 'Sky  UK Limited',
    'outer spaces' => '  Sky UK Limited ',
]);

it('needs the full register name for a known employer', function (string $name) {
    expect(classifier()->classify($name, []))->toBeNull();
})->with([
    'missing UK' => 'Sky Limited',
    'Ltd instead of Limited' => 'Sky UK Ltd',
]);

it('gives null with no tech code, no keyword, and not a known employer', function () {
    expect(classifier()->classify('Acme Widgets Ltd', ['47110']))->toBeNull();
});

it('gives null for empty codes and an empty name', function () {
    expect(classifier()->classify('', []))->toBeNull();
});

it('matches keywords as whole words only', function (string $name) {
    expect(classifier()->classify($name, []))->toBeNull();
})->with([
    'ai inside maintenance' => 'Maintenance Services Ltd',
    'web inside webster' => 'Webster Holdings Ltd',
    'data inside database' => 'Databased Ltd',
    'tech inside technician' => 'Technician Solutions Ltd',
    'apps inside happs' => 'Happs Ltd',
]);

it('uses the lists it is given', function () {
    $classifier = new EmployerClassifier(
        techSicCodes: ['99999'],
        techKeywords: ['robotics'],
        noiseKeywords: ['toy'],
        knownEmployers: ['Example Corp Limited'],
    );

    expect($classifier->classify('Acme Ltd', ['99999']))->toBe(TechReason::Sic)
        ->and($classifier->classify('Acme Ltd', ['62012']))->toBeNull()
        ->and($classifier->classify('Acme Robotics Ltd', []))->toBe(TechReason::Keyword)
        ->and($classifier->classify('Acme Toy Robotics Ltd', []))->toBeNull()
        ->and($classifier->classify('Acme Software Ltd', []))->toBeNull()
        ->and($classifier->classify('EXAMPLE CORP LIMITED', []))->toBe(TechReason::KnownEmployer);
});

it('reads the lists from config when resolved from the container', function () {
    config(['sponsor-finder.tech_keywords' => ['robotics']]);

    expect(classifier()->classify('Acme Robotics Ltd', []))->toBe(TechReason::Keyword)
        ->and(classifier()->classify('Acme Software Ltd', []))->toBeNull();
});

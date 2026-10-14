<?php

declare(strict_types=1);

use App\Enums\VisaSponsorship;
use App\Support\CareersSignals;
use Tests\TestCase;

uses(TestCase::class);

function careersSignals(): CareersSignals
{
    return app(CareersSignals::class);
}

it('finds developer roles', function (string $text) {
    expect(careersSignals()->mentionsDeveloperRoles($text))->toBeTrue();
})->with([
    'developer' => ['We are hiring a developer'],
    'plural' => ['Open roles: Software Engineers'],
    'hyphenated full stack' => ['Full-Stack Developer (Remote)'],
    'hyphenated back end' => ['Back-End engineer'],
    'node.js' => ['Experience with node.js'],
    'nodejs' => ['Experience with NodeJS'],
    'node js' => ['Experience with Node JS'],
    'php' => ['PHP and Laravel'],
    'typescript' => ['TypeScript across the stack'],
    'ai engineer' => ['AI Engineer, London'],
    'data scientist' => ['Data Scientists wanted'],
    'engineering team' => ['Join our engineering team'],
]);

it('does not find developer roles in other wording', function (string $text) {
    expect(careersSignals()->mentionsDeveloperRoles($text))->toBeFalse();
})->with([
    'a node in the network' => ['Every branch is a node in the network'],
    'phpunit alone' => ['Our tests run on phpunit'],
    'no roles' => ['We are hiring care assistants and drivers'],
    'empty' => [''],
]);

it('reads visa sponsorship', function (string $text, ?VisaSponsorship $expected) {
    expect(careersSignals()->visaSponsorship($text))->toBe($expected);
})->with([
    'offer' => ['Visa sponsorship available for this role', VisaSponsorship::Offered],
    'skilled worker' => ['We can support a Skilled Worker visa', VisaSponsorship::Offered],
    'refusal' => ['We are unable to offer visa sponsorship', VisaSponsorship::NotOffered],
    'refusal with curly apostrophe' => ["We can\u{2019}t sponsor visas", VisaSponsorship::NotOffered],
    'offer and refusal' => ['Visa sponsorship is great. We are unable to offer visa sponsorship.', VisaSponsorship::NotOffered],
    'right to work alone' => ['You must have the right to work in the UK', VisaSponsorship::NotOffered],
    'offer and right to work' => ['Visa sponsorship available. You must have the right to work in the UK or need sponsorship.', VisaSponsorship::Offered],
    'event sponsorship' => ['Our event sponsorship programme supports local clubs', null],
    'no visa wording' => ['We are hiring developers', null],
    'empty' => ['', null],
]);

it('uses the configured terms', function () {
    config([
        'sponsor-finder.developer_role_terms' => ['rust'],
        'sponsor-finder.visa_offer_terms' => ['global talent'],
        'sponsor-finder.visa_refusal_terms' => [],
        'sponsor-finder.right_to_work_terms' => [],
    ]);

    expect(careersSignals()->mentionsDeveloperRoles('rust engineers'))->toBeTrue()
        ->and(careersSignals()->mentionsDeveloperRoles('php developer'))->toBeFalse()
        ->and(careersSignals()->visaSponsorship('global talent visa'))->toBe(VisaSponsorship::Offered)
        ->and(careersSignals()->visaSponsorship('unable to sponsor'))->toBeNull();
});

<?php

declare(strict_types=1);

use App\Services\SearchResult;
use App\Services\VerifiedPage;
use App\Services\WebsiteCandidate;
use App\Services\WebsiteScorer;
use Tests\TestCase;

uses(TestCase::class);

function scorer(): WebsiteScorer
{
    return app(WebsiteScorer::class);
}

function result(string $url, string $title = ''): SearchResult
{
    return new SearchResult($url, $title);
}

it('scores FourJaw by first word in the host and title', function () {
    $best = scorer()->best('FourJaw Manufacturing Analytics Ltd', [
        result('https://uk.linkedin.com/company/fourjaw', 'FourJaw | LinkedIn'),
        result('https://fourjaw.com/about-us', 'About FourJaw | Machine Monitoring Software'),
        result('https://www.themanufacturer.com/articles/fourjaw-raises/', 'Sheffield start-up raises funding'),
    ]);

    expect($best)->toEqual(new WebsiteCandidate('https://fourjaw.com', 55));
});

it('scores The Floow by the full name in the host, ignoring "the"', function () {
    $best = scorer()->best('The Floow Limited', [
        result('https://find-and-update.company-information.service.gov.uk/company/07034516', 'THE FLOOW LIMITED overview'),
        result('https://www.thefloow.com/', 'The Floow | Connected insurance telematics'),
        result('https://www.crunchbase.com/organization/the-floow', 'The Floow - Crunchbase'),
    ]);

    expect($best)->toEqual(new WebsiteCandidate('https://www.thefloow.com', 75));
});

it('scores Sumo Digital by the full name in a hyphenated host', function () {
    $best = scorer()->best('Sumo Digital Ltd', [
        result('https://en.wikipedia.org/wiki/Sumo_Digital', 'Sumo Digital - Wikipedia'),
        result('https://www.sumo-digital.com/careers/', 'Careers | Sumo Digital'),
        result('https://www.sumogroupplc.com/', 'Sumo Group plc'),
    ]);

    expect($best)->toEqual(new WebsiteCandidate('https://www.sumo-digital.com', 75));
});

it('picks the highest scorer, not the first result', function () {
    $best = scorer()->best('Acme Software Ltd', [
        result('https://www.acmeplumbing.co.uk/', 'Plumbers in Leeds'),
        result('https://acmesoftware.co.uk/contact', 'Acme Software'),
    ]);

    expect($best?->url)->toBe('https://acmesoftware.co.uk')
        ->and($best?->score)->toBe(75);
});

it('keeps the earlier result on a tie', function () {
    $best = scorer()->best('Acme Software Ltd', [
        result('https://acmesoftware.co.uk/', 'Home'),
        result('https://acmesoftware.com/', 'Home'),
    ]);

    expect($best?->url)->toBe('https://acmesoftware.co.uk');
});

it('gives 15 for the first word in the title alone', function () {
    $best = scorer()->best('Acme Software Ltd', [result('https://www.example.co.uk/', 'Acme Software, Sheffield')]);

    expect($best)->toEqual(new WebsiteCandidate('https://www.example.co.uk', 15));
});

it('matches the first word in the title as a whole word only', function () {
    expect(scorer()->best('Acme Software Ltd', [result('https://www.example.co.uk/', 'Acmes and more')]))->toBeNull();
});

it('returns null when no result scores', function () {
    expect(scorer()->best('Acme Software Ltd', [result('https://www.example.co.uk/', 'Something else')]))->toBeNull();
});

it('returns null for no results or a name that normalises to nothing', function () {
    expect(scorer()->best('Acme Software Ltd', []))->toBeNull()
        ->and(scorer()->best('The Group Ltd', [result('https://www.thegroup.com/', 'The Group')]))->toBeNull();
});

it('does not match a short first word inside the host', function () {
    expect(scorer()->best('IT Solutions Sheffield Ltd', [result('https://www.digital-sheffield.co.uk/', 'Home')]))->toBeNull();
});

it('matches a short first word at the start of a host label', function () {
    expect(scorer()->best('A1 Taxis Sheffield Ltd', [result('https://a1sheffieldtaxis.co.uk/', 'A1 Taxis Sheffield | Local Taxi')]))
        ->toEqual(new WebsiteCandidate('https://a1sheffieldtaxis.co.uk', 55));
});

it('still matches a short name in full', function () {
    expect(scorer()->best('BT Group', [result('https://www.bt.com/', 'BT Broadband')]))
        ->toEqual(new WebsiteCandidate('https://www.bt.com', 75));
});

it('returns the homepage as scheme and lowercase host', function () {
    $best = scorer()->best('Acme Software Ltd', [result('HTTP://WWW.AcmeSoftware.co.uk:8080/a/b?c=d#e', 'Acme')]);

    expect($best?->url)->toBe('http://www.acmesoftware.co.uk');
});

it('ignores results that are not http or https links', function () {
    expect(scorer()->best('Acme Software Ltd', [
        result('ftp://acmesoftware.co.uk/', 'Acme'),
        result('/acmesoftware', 'Acme'),
    ]))->toBeNull();
});

it('never returns a blocked domain, however well it scores', function (string $url) {
    $results = [result($url, 'Acme Software Ltd')];

    expect(scorer()->best('Acme Software Ltd', $results))->toBeNull();
})->with([
    'linkedin' => 'https://uk.linkedin.com/company/acmesoftware',
    'facebook' => 'https://www.facebook.com/acmesoftware',
    'twitter' => 'https://twitter.com/acmesoftware',
    'x.com' => 'https://x.com/acmesoftware',
    'instagram' => 'https://www.instagram.com/acmesoftware',
    'youtube' => 'https://www.youtube.com/@acmesoftware',
    'wikipedia' => 'https://en.wikipedia.org/wiki/Acmesoftware',
    'companies house' => 'https://find-and-update.company-information.service.gov.uk/company/acmesoftware',
    'gov.uk' => 'https://www.acmesoftware.gov.uk/',
    'endole' => 'https://open.endole.co.uk/insight/company/acmesoftware',
    'opencorporates' => 'https://opencorporates.com/companies/gb/acmesoftware',
    'crunchbase' => 'https://www.crunchbase.com/organization/acmesoftware',
    'dealroom' => 'https://app.dealroom.co/companies/acmesoftware',
    'prospeo' => 'https://prospeo.io/c/acmesoftware',
    'glassdoor' => 'https://www.glassdoor.co.uk/Overview/acmesoftware',
    'indeed' => 'https://uk.indeed.com/cmp/acmesoftware',
    'reed.co.uk' => 'https://www.reed.co.uk/jobs/acmesoftware',
    'yell.com' => 'https://www.yell.com/biz/acmesoftware-sheffield/',
    'bloomberg' => 'https://www.bloomberg.com/profile/company/acmesoftware',
    'zoominfo' => 'https://www.zoominfo.com/c/acmesoftware',
    'rocketreach' => 'https://rocketreach.co/acmesoftware',
    'cylex' => 'https://sheffield.cylex-uk.co.uk/company/acmesoftware.html',
    'misterwhat' => 'https://www.misterwhat.co.uk/company/acmesoftware-sheffield',
    'companycheck' => 'http://companycheck.co.uk/company/acmesoftware',
    'lursoft' => 'https://ukcompanies.lursoft.lv/acmesoftware',
    'companiesintheuk' => 'https://www.companiesintheuk.co.uk/ltd/acmesoftware',
    'dnb.com' => 'https://www.dnb.com/business-directory/acmesoftware',
    'kompass' => 'https://gb.kompass.com/c/acmesoftware',
    '192.com' => 'https://www.192.com/atoz/business/acmesoftware',
    'thomsonlocal' => 'https://www.thomsonlocal.com/search/acmesoftware',
    'nextdoor' => 'https://nextdoor.co.uk/pages/acmesoftware',
    'mapcarta' => 'https://mapcarta.com/acmesoftware',
    'mapquest' => 'https://www.mapquest.com/gb/acmesoftware',
    'maps.apple.com' => 'https://maps.apple.com/place?q=acmesoftware',
    'automapa' => 'https://gb.automapa.com/acmesoftware',
    'azure-book.com' => 'https://acmesoftware.azure-book.com/',
    'licensed-sponsors-uk' => 'https://licensed-sponsors-uk.com/acmesoftware',
    'checkall' => 'https://www.checkall.co.uk/acmesoftware',
    'immigrationgpt' => 'https://immigrationgpt.co.uk/acmesoftware',
    'huntukvisasponsors' => 'https://huntukvisasponsors.com/acmesoftware',
    'sponsorlicensechecker' => 'https://sponsorlicensechecker.uk/acmesoftware',
    'ukvisasponsorshipchecker' => 'https://ukvisasponsorshipchecker.com/acmesoftware',
    'ukmovecheck' => 'https://ukmovecheck.co.uk/acmesoftware',
    'visapath' => 'https://visapath.co.uk/acmesoftware',
    'restaurantguru' => 'https://restaurantguru.com/acmesoftware',
    'halalresmenu' => 'https://acmesoftware.halalresmenu.com/',
    'wheree' => 'https://acmesoftware.wheree.com/',
    'hungryfoody' => 'https://www.hungryfoody.com/acmesoftware',
    'halalguide' => 'https://en.halalguide.me/acmesoftware',
    'www.nhs.uk' => 'https://www.nhs.uk/services/acmesoftware',
    'cqc.org.uk' => 'https://www.cqc.org.uk/location/acmesoftware',
    'carechoices.co.uk' => 'https://www.carechoices.co.uk/acmesoftware',
    'autumna' => 'https://www.autumna.co.uk/acmesoftware',
    'dentalchoices' => 'https://dentalchoices.org/acmesoftware',
    'ouch.ai' => 'https://www2.ouch.ai/acmesoftware',
    'companyjobs' => 'https://companyjobs.co.uk/acmesoftware',
]);

it('checks blocked domains against the host, not the whole URL', function (string $url) {
    expect(scorer()->best('Acme Software Ltd', [result($url, 'Acme Software')]))->not->toBeNull();
})->with([
    'x.com inside another domain' => 'https://www.acmesoftwarebox.com/',
    'reed.co.uk inside another domain' => 'https://acmesoftware-freed.co.uk/',
    'blocked word in the path' => 'https://acmesoftware.co.uk/linkedin',
    'apple.com outside maps' => 'https://www.acmesoftware.apple.com/',
    'an NHS trust outside www.nhs.uk' => 'https://www.acmesoftware.nhs.uk/',
]);

it('reads blocked domains from config', function () {
    $scorer = new WebsiteScorer(['acmesoftware.co.uk']);

    expect($scorer->best('Acme Software Ltd', [result('https://www.acmesoftware.co.uk/', 'Acme')]))->toBeNull()
        ->and(config('sponsor-finder.blocked_domains'))->toContain('cylex', 'misterwhat', 'companycheck', 'x.com');
});

it('adds 25 to the confidence when the page mentions the name, capped at 100', function () {
    $named = new VerifiedPage(true, 'acme');
    $unnamed = new VerifiedPage(false, '');

    expect(scorer()->confidence(new WebsiteCandidate('https://acme.co.uk', 55), $named))->toBe(80)
        ->and(scorer()->confidence(new WebsiteCandidate('https://acme.co.uk', 55), $unnamed))->toBe(55)
        ->and(scorer()->confidence(new WebsiteCandidate('https://acme.co.uk', 100), $named))->toBe(100);
});

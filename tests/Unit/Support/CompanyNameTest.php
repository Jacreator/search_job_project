<?php

declare(strict_types=1);

use App\Support\CompanyName;

it('normalises a company name', function (string $name, string $expected) {
    expect(CompanyName::normalise($name))->toBe($expected);
})->with([
    'lowercases' => ['ACME SOFTWARE', 'acme software'],
    'removes limited' => ['Acme Software Limited', 'acme software'],
    'removes ltd with a full stop' => ['Acme Software Ltd.', 'acme software'],
    'removes plc' => ['Rightmove plc', 'rightmove'],
    'removes llp' => ['Deloitte LLP', 'deloitte'],
    'removes uk in brackets' => ['Accenture (UK) Limited', 'accenture'],
    'removes the' => ['The Hut Group Limited', 'hut'],
    'removes group and holdings' => ['Acme Group Holdings Ltd', 'acme'],
    'drops the name after t/a' => ['Acme Ltd t/a Widget World', 'acme'],
    'drops the name after T/A with spaces' => ['ACME LTD T / A WIDGET WORLD', 'acme'],
    'drops the name after t/as' => ['Aadipranavam Ltd T/as Durga Store', 'aadipranavam'],
    "drops the name after t/a's" => ["Suresh Sivarasa T/A's North Petherton Village Store", 'suresh sivarasa'],
    'keeps a dotted t.a inside a name' => ['I.T.A Tax Accounting', 'i t a tax accounting'],
    'drops the name after trading as' => ['John Smith trading as Smith Digital', 'john smith'],
    'removes punctuation' => ['Jet2.com Limited', 'jet2 com'],
    'joins apostrophes' => ["Sainsbury's Supermarkets Ltd", 'sainsburys supermarkets'],
    'joins curly apostrophes' => ["Sainsbury\u{2019}s Supermarkets Ltd", 'sainsburys supermarkets'],
    'splits on ampersands' => ['Ernst & Young LLP', 'ernst young'],
    'collapses spaces' => ['  Acme    Software   Ltd  ', 'acme software'],
    'keeps removed words inside other words' => ['Groupon Ltd', 'groupon'],
    'keeps letters that look like t/a inside words' => ['Data Analytics Ltd', 'data analytics'],
    'can normalise to nothing' => ['The Group Ltd', ''],
]);

it('gives the register name and the Companies House title the same form', function () {
    expect(CompanyName::normalise('Acme Software (UK) Limited'))
        ->toBe(CompanyName::normalise('ACME SOFTWARE LTD'));
});

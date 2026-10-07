<?php

declare(strict_types=1);

use App\Support\SearchQuery;

it('joins the sponsor name and town', function () {
    expect(SearchQuery::for('Acme Software Ltd', 'Sheffield'))->toBe('Acme Software Ltd Sheffield');
});

it('keeps the original sponsor name, not the normalised one', function () {
    expect(SearchQuery::for("Sainsbury's Supermarkets Ltd t/a Sainsbury's", 'London'))
        ->toBe("Sainsbury's Supermarkets Ltd t/a Sainsbury's London");
});

it('uses the name alone when the town is missing', function (?string $town) {
    expect(SearchQuery::for('Acme Software Ltd', $town))->toBe('Acme Software Ltd');
})->with([
    'empty' => [''],
    'blank' => ['  '],
    'null' => [null],
]);

it('trims the name and town', function () {
    expect(SearchQuery::for(' Acme Software Ltd ', ' Sheffield '))->toBe('Acme Software Ltd Sheffield');
});

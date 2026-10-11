<?php

declare(strict_types=1);

use App\Support\CsvCell;

it('prefixes a cell that starts a formula with an apostrophe', function (string $value) {
    expect(CsvCell::safe($value))->toBe("'".$value);
})->with([
    '=HYPERLINK("http://evil.test","Click")',
    '+44 114 000 0000',
    '-1+1',
    '@SUM(A1:A2)',
]);

it('leaves other cells as they are', function (string $value) {
    expect(CsvCell::safe($value))->toBe($value);
})->with([
    'Acme Software Ltd',
    'https://acme.test',
    'A=B Ltd',
    'Marks and Spencer - Digital',
    '62012, 62020',
    ' =not at the start',
]);

it('turns null into an empty cell', function () {
    expect(CsvCell::safe(null))->toBe('')
        ->and(CsvCell::safe(''))->toBe('');
});

<?php

declare(strict_types=1);

use App\Support\TermMatcher;

it('matches a whole word or a whole phrase', function (string $text, array $terms) {
    expect(TermMatcher::contains($text, $terms))->toBeTrue();
})->with([
    'word' => ['We build AI tools', ['ai']],
    'phrase' => ['Experts in machine learning and data', ['machine learning']],
    'term at the start' => ['ai first', ['ai']],
    'term at the end' => ['built with ai', ['ai']],
    'next to punctuation' => ['Tools (AI), apps.', ['ai']],
    'any of several terms' => ['robotics lab', ['nlp', 'robotics']],
    'phrase across extra spaces' => ["machine \n  learning", ['machine learning']],
]);

it('matches a plural "s"', function (string $text, string $term) {
    expect(TermMatcher::contains($text, [$term]))->toBeTrue();
})->with([
    'developers' => ['Hiring developers now', 'developer'],
    'LLMs' => ['We fine-tune LLMs', 'llm'],
]);

it('matches hyphen and space forms of each other', function (string $text, string $term) {
    expect(TermMatcher::contains($text, [$term]))->toBeTrue();
})->with([
    'ai-powered term, spaced text' => ['AI powered search', 'ai-powered'],
    'ai-powered term, hyphenated text' => ['AI-powered search', 'ai-powered'],
    'spaced term, hyphenated text' => ['AI-powered search', 'ai powered'],
    'full-stack term, spaced text' => ['full stack engineer', 'full-stack'],
    'full stack term, hyphenated text' => ['full-stack engineer', 'full stack'],
]);

it('matches curly and straight apostrophes', function (string $text, string $term) {
    expect(TermMatcher::contains($text, [$term]))->toBeTrue();
})->with([
    'curly text, straight term' => ["We\u{2019}re hiring", "we're hiring"],
    'straight text, curly term' => ["We're hiring", "we\u{2019}re hiring"],
]);

it('does not match inside other words', function (string $text, string $term) {
    expect(TermMatcher::contains($text, [$term]))->toBeFalse();
})->with([
    'ai in email' => ['Send us an email', 'ai'],
    'ai in maintain' => ['We maintain systems', 'ai'],
    'ml in html' => ['HTML and CSS', 'ml'],
    'php in phpunit' => ['We use phpunit', 'php'],
    'ai with a digit' => ['ai2 labs', 'ai'],
    'ai with an underscore' => ['my_ai_tool', 'ai'],
]);

it('treats dots in a term as literal', function () {
    expect(TermMatcher::contains('We use node.js daily', ['node.js']))->toBeTrue()
        ->and(TermMatcher::contains('We run a node network', ['node.js']))->toBeFalse()
        ->and(TermMatcher::contains('We use nodexjs daily', ['node.js']))->toBeFalse();
});

it('is false for empty text or no terms', function (string $text, array $terms) {
    expect(TermMatcher::contains($text, $terms))->toBeFalse();
})->with([
    'empty text' => ['', ['ai']],
    'blank text' => ["  \n ", ['ai']],
    'no terms' => ['We build AI tools', []],
    'only blank terms' => ['We build AI tools', ['', ' ', '-']],
]);

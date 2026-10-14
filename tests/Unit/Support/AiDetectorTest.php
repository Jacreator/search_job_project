<?php

declare(strict_types=1);

use App\Support\AiDetector;
use Tests\TestCase;

uses(TestCase::class);

function aiDetector(): AiDetector
{
    return app(AiDetector::class);
}

it('finds every AI term in page text', function (string $text) {
    expect(aiDetector()->fromText($text))->toBeTrue();
})->with([
    'ai' => ['we build ai tools'],
    'artificial intelligence' => ['artificial intelligence for retail'],
    'machine learning' => ['machine learning models'],
    'ml' => ['ml engineers'],
    'deep learning' => ['deep learning research'],
    'llm' => ['our llm platform'],
    'llms plural' => ['we fine tune llms'],
    'large language model' => ['a large language model for law'],
    'genai' => ['genai consulting'],
    'generative' => ['generative design'],
    'computer vision' => ['computer vision for farms'],
    'nlp' => ['nlp pipelines'],
    'data science' => ['data science team'],
    'robotics' => ['warehouse robotics'],
    'mlops' => ['mlops platform'],
    'ai-powered' => ['ai-powered search'],
    'AI-Powered capitals' => ['AI-Powered Search'],
]);

it('finds AI terms in the company name', function (string $name) {
    expect(aiDetector()->fromName($name))->toBeTrue();
})->with([
    'Faculty AI Ltd',
    'Sheffield Robotics Limited',
    'GenAI Labs Ltd',
    'Acme Machine Learning Ltd',
    'AI-Powered Solutions Ltd',
    'Acme Data Science Group plc',
]);

it('does not tag names or text without an AI term', function () {
    expect(aiDetector()->fromName('Acme Software Ltd'))->toBeFalse()
        ->and(aiDetector()->fromName('Main Street Mail Ltd'))->toBeFalse()
        ->and(aiDetector()->fromName('Sai Care Ltd'))->toBeFalse()
        ->and(aiDetector()->fromText('email us to maintain your html site'))->toBeFalse()
        ->and(aiDetector()->fromText(''))->toBeFalse();
});

it('uses the configured terms', function () {
    config(['sponsor-finder.ai_keywords' => ['quantum']]);

    expect(aiDetector()->fromText('quantum computing'))->toBeTrue()
        ->and(aiDetector()->fromText('we build ai tools'))->toBeFalse();
});

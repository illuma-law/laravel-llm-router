<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Tests\Feature;

use IllumaLaw\LlmRouter\Contracts\ProviderAvailability;
use IllumaLaw\LlmRouter\Support\ChainRowValidator;
use IllumaLaw\LlmRouter\Support\ConfigProviderAvailability;
use IllumaLaw\LlmRouter\Support\CooldownStore;
use Illuminate\Validation\ValidationException;

it('normalizes and deduplicates chain rows', function () {
    $validator = new ChainRowValidator(['openai', 'voyageai']);

    $rows = $validator->normalizeRows(
        rows: [
            ['provider' => 'OpenAI', 'model' => 'gpt-5.4', 'enabled' => true],
            ['provider' => 'OpenAI', 'model' => 'gpt-5.4', 'enabled' => true],
            ['provider' => 'voyage', 'model' => 'voyage-3.5-lite', 'enabled' => false],
        ],
        patternsByProvider: [
            'openai'   => ['/^[A-Za-z0-9._:-]+$/'],
            'voyageai' => ['/^[A-Za-z0-9._:-]+$/'],
        ],
    );

    expect($rows)->toBe([
        ['provider' => 'openai', 'model' => 'gpt-5.4', 'enabled' => true],
        ['provider' => 'voyageai', 'model' => 'voyage-3.5-lite', 'enabled' => false],
    ]);
});

it('throws validation exception for unknown providers', function () {
    $validator = new ChainRowValidator(['openai']);

    expect(fn () => $validator->normalizeRows([['provider' => 'gemini', 'model' => 'x', 'enabled' => true]]))
        ->toThrow(ValidationException::class);
});

it('throws validation exception for malformed row payload', function () {
    $validator = new ChainRowValidator(['openai']);

    expect(fn () => $validator->normalizeRows('invalid'))
        ->toThrow(ValidationException::class)
        ->and(fn () => $validator->normalizeRows([['provider' => 'openai', 'model' => 'ok'], 'bad-row']))
        ->toThrow(ValidationException::class);
});

it('supports allowlist-only model validation', function () {
    $validator = new ChainRowValidator(['openai']);

    $rows = $validator->normalizeRows(
        rows: [
            ['provider' => 'openai', 'model' => 'gpt-5.4', 'enabled' => true],
        ],
        patternsByProvider: [
            'openai' => [],
        ],
        allowlistByProvider: [
            'openai' => ['gpt-5.4', 'gpt-5.4-mini'],
        ],
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['model'])->toBe('gpt-5.4')
        ->and(fn () => $validator->normalizeRows(
            rows: [['provider' => 'openai', 'model' => 'unknown-model', 'enabled' => true]],
            patternsByProvider: ['openai' => []],
            allowlistByProvider: ['openai' => ['gpt-5.4']],
        ))->toThrow(ValidationException::class);
});

it('requires at least one enabled row', function () {
    $validator = new ChainRowValidator(['openai']);

    expect(fn () => $validator->ensureHasEnabledRows([
        ['provider' => 'openai', 'model' => 'gpt-5.4', 'enabled' => false],
    ]))->toThrow(ValidationException::class);
});

it('checks provider availability from configurable paths', function () {
    config()->set('custom.providers.openai', ['key' => 'key']);
    config()->set('custom.providers.ollama', ['url' => 'http://ollama:11434']);
    config()->set('custom.providers.azure', ['key' => 'azure-key', 'url' => 'https://example.azure.com']);
    config()->set('custom.toggles.openai', true);
    config()->set('custom.toggles.ollama', false);

    $availability = new ConfigProviderAvailability(
        providersConfigPath: 'custom.providers',
        togglesConfigPath: 'custom.toggles',
        aliases: ['open-ai' => 'openai'],
    );

    expect($availability->hasCredentials('open-ai'))->toBeTrue()
        ->and($availability->hasCredentials('ollama'))->toBeTrue()
        ->and($availability->hasCredentials('azure'))->toBeTrue()
        ->and($availability->hasCredentials(new class
        {
            public function __toString(): string
            {
                return 'not-scalar-internally';
            }
        }))->toBeFalse()
        ->and($availability->isEnabled('openai'))->toBeTrue()
        ->and($availability->isEnabled('ollama'))->toBeFalse()
        ->and($availability->isEnabled('azure'))->toBeTrue();

    config()->set('custom.providers.azure', ['key' => 'azure-key']);

    expect($availability->hasCredentials('azure'))->toBeFalse();
});

it('tracks cooldown lifecycle in cache', function () {
    config()->set('cache.default', 'array');
    $store = new CooldownStore('test-router');

    expect($store->shouldSkip('text', 'openai', 'gpt-5.4'))->toBeFalse();

    $store->recordRetryableFailure('text', 'openai', 'gpt-5.4', threshold: 2, cooldownSeconds: 60, failureCounterTtlSeconds: 60);
    expect($store->shouldSkip('text', 'openai', 'gpt-5.4'))->toBeFalse();

    $store->recordRetryableFailure('text', 'openai', 'gpt-5.4', threshold: 2, cooldownSeconds: 60, failureCounterTtlSeconds: 60);
    expect($store->shouldSkip('text', 'openai', 'gpt-5.4'))->toBeTrue();

    $store->recordSuccess('text', 'openai', 'gpt-5.4');
    expect($store->shouldSkip('text', 'openai', 'gpt-5.4'))->toBeFalse();
});

it('isolates cooldown keys by profile provider and model', function () {
    config()->set('cache.default', 'array');
    $store = new CooldownStore('test-router');

    $store->recordRetryableFailure('text', 'openai', 'gpt-5.4', threshold: 1, cooldownSeconds: 60, failureCounterTtlSeconds: 60);

    expect($store->shouldSkip('text', 'openai', 'gpt-5.4'))->toBeTrue()
        ->and($store->shouldSkip('embeddings', 'openai', 'gpt-5.4'))->toBeFalse()
        ->and($store->shouldSkip('text', 'anthropic', 'gpt-5.4'))->toBeFalse()
        ->and($store->shouldSkip('text', 'openai', 'gpt-5.4-mini'))->toBeFalse();
});

it('registers support utilities in the container', function () {
    config()->set('llm-router.providers.openai', ['key' => 'key']);
    config()->set('llm-router.toggles.openai', true);

    $contract = app(ProviderAvailability::class);
    $concrete = app(ConfigProviderAvailability::class);
    $validator = app(ChainRowValidator::class);
    $cooldown = app(CooldownStore::class);

    expect($contract)->toBeInstanceOf(ConfigProviderAvailability::class)
        ->and($concrete)->toBeInstanceOf(ConfigProviderAvailability::class)
        ->and($validator)->toBeInstanceOf(ChainRowValidator::class)
        ->and($cooldown)->toBeInstanceOf(CooldownStore::class)
        ->and($contract->hasCredentials('openai'))->toBeTrue();
});

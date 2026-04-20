<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Tests\Feature;

use IllumaLaw\LlmRouter\Enums\DefaultTier;
use IllumaLaw\LlmRouter\Facades\LLMRouter;

it('can initiate a request through the facade', function () {
    /** @var array{result: mixed, provider: string, model: string, provider_label: string} $result */
    $result = LLMRouter::tier(DefaultTier::Large)
        ->run(function (string $provider, string $model) {
            return "Result: {$provider}/{$model}";
        });

    expect($result['result'])->toBe('Result: anthropic/claude-3-5-sonnet-latest');
});

it('supports tenant context in the request builder', function () {
    LLMRouter::resolvePriorityUsing(fn (mixed $tenant) => is_array($tenant) && ($tenant['has_priority'] ?? false) === true);

    /** @var array{result: mixed, provider: string, model: string, provider_label: string} $result */
    $result = LLMRouter::tier(DefaultTier::Large)
        ->forTenant(['id' => 1, 'has_priority' => true])
        ->run(function (string $provider, string $model) {
            return "Result: {$provider}/{$model}";
        });

    expect($result['result'])->toBe('Result: ollama/llama3.1:70b');
});

it('supports operation context in the request builder', function () {
    /** @var array{result: mixed, provider: string, model: string, provider_label: string} $result */
    $result = LLMRouter::operation('image_generation')
        ->run(function (string $provider, string $model) {
            return "Result: {$provider}/{$model}";
        });

    expect($result['result'])->toBe('Result: openai/dall-e-3');
});

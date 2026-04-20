<?php

declare(strict_types=1);

use IllumaLaw\LlmRouter\ChainResolver;
use IllumaLaw\LlmRouter\Contracts\AiTier;
use IllumaLaw\LlmRouter\Enums\DefaultTier;
use IllumaLaw\LlmRouter\Facades\LLMRouter;

it('resolves the correct chain for large tier', function () {
    $resolver = app(ChainResolver::class);
    $chain = $resolver->resolve(tier: DefaultTier::Large);

    expect($chain)->toHaveCount(3)
        ->and($chain[0]['provider'])->toBe('anthropic')
        ->and($chain[0]['model'])->toBe('claude-3-5-sonnet-latest')
        ->and($chain[1]['provider'])->toBe('openai')
        ->and($chain[1]['model'])->toBe('gpt-4o');
});

it('resolves the correct chain for small tier', function () {
    $resolver = app(ChainResolver::class);
    $chain = $resolver->resolve(tier: DefaultTier::Small);

    expect($chain)->toHaveCount(3)
        ->and($chain[0]['provider'])->toBe('anthropic')
        ->and($chain[0]['model'])->toBe('claude-3-5-haiku-latest')
        ->and($chain[1]['provider'])->toBe('openai')
        ->and($chain[1]['model'])->toBe('gpt-4o-mini');
});

it('resolves the correct chain for a specific operation', function () {
    $resolver = app(ChainResolver::class);
    $chain = $resolver->resolve(operation: 'image_generation');

    expect($chain)->toHaveCount(1)
        ->and($chain[0]['provider'])->toBe('openai')
        ->and($chain[0]['model'])->toBe('dall-e-3');
});

it('prioritizes operation chain over tier chain', function () {
    $resolver = app(ChainResolver::class);
    $chain = $resolver->resolve(tier: 'large', operation: 'image_generation');

    expect($chain)->toHaveCount(1)
        ->and($chain[0]['provider'])->toBe('openai')
        ->and($chain[0]['model'])->toBe('dall-e-3');
});

it('applies priority override when tenant has priority', function () {
    LLMRouter::resolvePriorityUsing(fn ($tenant) => $tenant === 'priority-tenant');

    $resolver = app(ChainResolver::class);

    $chain = $resolver->resolve(tier: 'large', tenant: 'priority-tenant');
    expect($chain)->toHaveCount(1)
        ->and($chain[0]['provider'])->toBe('ollama')
        ->and($chain[0]['model'])->toBe('llama3.1:70b');

    $chain = $resolver->resolve(tier: DefaultTier::Large, tenant: 'regular-tenant');
    expect($chain[0]['provider'])->toBe('anthropic');
});

it('resolves a custom tier', function () {
    config()->set('llm-router.tiers.custom', [
        ['provider' => 'google', 'model' => 'gemini-1.5-flash'],
    ]);

    $resolver = app(ChainResolver::class);

    $chain = $resolver->resolve(tier: 'custom');
    expect($chain)->toHaveCount(1)
        ->and($chain[0]['provider'])->toBe('google');

    $customTier = new class implements AiTier
    {
        public function value(): string
        {
            return 'custom';
        }
    };

    $chain = $resolver->resolve(tier: $customTier);
    expect($chain)->toHaveCount(1)
        ->and($chain[0]['provider'])->toBe('google');
});

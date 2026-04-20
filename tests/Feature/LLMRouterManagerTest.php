<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Tests\Feature;

use IllumaLaw\LlmRouter\Contracts\ChainRepository;
use IllumaLaw\LlmRouter\LLMRouterManager;
use IllumaLaw\LlmRouter\PendingLlmRequest;
use IllumaLaw\LlmRouter\Enums\DefaultTier;
use Mockery;

it('can set and resolve priority using a closure', function () {
    $manager = app(LLMRouterManager::class);
    
    expect($manager->hasPriority('any'))->toBeFalse();
    
    $manager->resolvePriorityUsing(fn($tenant) => $tenant === 'VIP');
    
    expect($manager->hasPriority('VIP'))->toBeTrue()
        ->and($manager->hasPriority('REGULAR'))->toBeFalse();
});

it('can set and resolve agent tier using a closure', function () {
    $manager = app(LLMRouterManager::class);
    
    expect($manager->resolveTierForAgent('AnyAgent'))->toBe(DefaultTier::Small);
    
    $manager->resolveAgentTierUsing(fn($agent) => $agent === 'PowerAgent' ? DefaultTier::Large : 'custom');
    
    expect($manager->resolveTierForAgent('PowerAgent'))->toBe(DefaultTier::Large)
        ->and($manager->resolveTierForAgent('OtherAgent'))->toBe('custom');
});

it('can use a custom repository', function () {
    $manager = app(LLMRouterManager::class);
    $repo = Mockery::mock(ChainRepository::class);
    
    $manager->useRepository($repo);
    
    // Internal verification is hard, but we can check if it flows through resolve
    $repo->shouldReceive('getAgentOverride')->andReturn(null);
    $repo->shouldReceive('getChain')->andReturn([['provider' => 'p', 'model' => 'm']]);
    
    $chain = $manager->resolve(tier: 'small');
    expect($chain)->toHaveCount(1)
        ->and($chain[0]['provider'])->toBe('p');
});

it('can initiate requests with various entry points', function () {
    $manager = app(LLMRouterManager::class);
    
    expect($manager->tier(DefaultTier::Large))->toBeInstanceOf(PendingLlmRequest::class);
    expect($manager->operation('test'))->toBeInstanceOf(PendingLlmRequest::class);
    expect($manager->forAgent('MyAgent'))->toBeInstanceOf(PendingLlmRequest::class);
});

it('can resolve using a custom closure', function () {
    $manager = app(LLMRouterManager::class);
    
    $manager->resolveChainUsing(fn() => [['provider' => 'custom', 'model' => 'model']]);
    
    $chain = $manager->resolve();
    expect($chain)->toHaveCount(1)
        ->and($chain[0]['provider'])->toBe('custom');
});

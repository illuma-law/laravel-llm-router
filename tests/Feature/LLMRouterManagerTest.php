<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Tests\Feature;

use IllumaLaw\LlmRouter\Contracts\ChainRepository;
use IllumaLaw\LlmRouter\Enums\DefaultTier;
use IllumaLaw\LlmRouter\LLMRouterManager;
use Mockery;
use Mockery\MockInterface;

it('can set and resolve priority using a closure', function () {
    $manager = app(LLMRouterManager::class);

    expect($manager->hasPriority('any'))->toBeFalse();

    $manager->resolvePriorityUsing(fn ($tenant) => $tenant === 'VIP');

    expect($manager->hasPriority('VIP'))->toBeTrue()
        ->and($manager->hasPriority('REGULAR'))->toBeFalse();
});

it('can set and resolve agent tier using a closure', function () {
    $manager = app(LLMRouterManager::class);

    expect($manager->resolveTierForAgent('AnyAgent'))->toBe(DefaultTier::Small);

    $manager->resolveAgentTierUsing(fn ($agent) => $agent === 'PowerAgent' ? DefaultTier::Large : 'custom');

    expect($manager->resolveTierForAgent('PowerAgent'))->toBe(DefaultTier::Large)
        ->and($manager->resolveTierForAgent('OtherAgent'))->toBe('custom');
});

it('can use a custom repository', function () {
    $manager = app(LLMRouterManager::class);
    /** @var ChainRepository&MockInterface $repo */
    $repo = Mockery::mock(ChainRepository::class, [
        'getAgentOverride' => null,
        'getChain' => [['provider' => 'p', 'model' => 'm']],
    ]);

    $manager->useRepository($repo);

    $chain = $manager->resolve(tier: 'small');
    expect($chain)->toHaveCount(1)
        ->and($chain[0]['provider'])->toBe('p');
});

it('can initiate requests with various entry points', function () {
    $manager = app(LLMRouterManager::class);

    expect($manager->tier(DefaultTier::Large))->not->toBeNull();
    expect($manager->operation('test'))->not->toBeNull();
    expect($manager->forAgent('MyAgent'))->not->toBeNull();
});

it('can resolve using a custom closure', function () {
    $manager = app(LLMRouterManager::class);

    $manager->resolveChainUsing(fn () => [['provider' => 'custom', 'model' => 'model']]);

    $chain = $manager->resolve();
    expect($chain)->toHaveCount(1)
        ->and($chain[0]['provider'])->toBe('custom');
});

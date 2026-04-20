<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Tests\Feature;

use IllumaLaw\LlmRouter\ChainResolver;
use IllumaLaw\LlmRouter\Contracts\ChainRepository;
use Mockery;

it('supports agent overrides from repository', function () {
    $resolver = app(ChainResolver::class);
    $repo = Mockery::mock(ChainRepository::class);
    $resolver->setRepository($repo);

    $repo->shouldReceive('getAgentOverride')
        ->with('SpecialAgent')
        ->andReturn(['provider' => 'special', 'model' => 'model']);
    
    $repo->shouldReceive('getChain')->andReturn([]);

    $chain = $resolver->resolve(agent: 'SpecialAgent');
    
    expect($chain[0]['provider'])->toBe('special');
});

it('removes duplicates from the chain', function () {
    $resolver = app(ChainResolver::class);
    $repo = Mockery::mock(ChainRepository::class);
    $resolver->setRepository($repo);

    $repo->shouldReceive('getAgentOverride')->andReturn(['provider' => 'p1', 'model' => 'm1']);
    $repo->shouldReceive('getChain')->andReturn([
        ['provider' => 'p1', 'model' => 'm1'],
        ['provider' => 'p2', 'model' => 'm2'],
    ]);

    $chain = $resolver->resolve(agent: 'Agent');
    
    expect($chain)->toHaveCount(2)
        ->and($chain[0]['provider'])->toBe('p1')
        ->and($chain[1]['provider'])->toBe('p2');
});

it('returns default small tier if final chain is empty', function () {
    config()->set('llm-router.tiers.small', [['provider' => 'default', 'model' => 'small']]);
    
    $resolver = app(ChainResolver::class);
    $repo = Mockery::mock(ChainRepository::class);
    $resolver->setRepository($repo);

    $repo->shouldReceive('getAgentOverride')->andReturn(null);
    $repo->shouldReceive('getChain')->andReturn([]);

    $chain = $resolver->resolve();
    
    expect($chain)->toHaveCount(1)
        ->and($chain[0]['provider'])->toBe('default');
});

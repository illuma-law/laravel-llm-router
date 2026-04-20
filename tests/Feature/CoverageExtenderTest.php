<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Tests\Feature;

use IllumaLaw\LlmRouter\ChainResolver;
use IllumaLaw\LlmRouter\ConfigChainRepository;
use IllumaLaw\LlmRouter\LLMRouterManager;
use IllumaLaw\LlmRouter\PendingLlmRequest;
use IllumaLaw\LlmRouter\ProviderNormalizer;
use IllumaLaw\LlmRouter\FailoverRunner;
use IllumaLaw\LlmRouter\FailureClassifier;
use Illuminate\Support\Facades\Facade;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

enum MockProviderEnum: string {
    case OpenAI = 'openai';
}

it('covers remaining lines in ChainResolver', function () {
    $manager = app(LLMRouterManager::class);
    $resolver = new ChainResolver($manager);
    
    $ref = new \ReflectionClass($resolver);
    $method = $ref->getMethod('getProviderValue');
    
    expect($method->invoke($resolver, MockProviderEnum::OpenAI))->toBe('openai')
        ->and($method->invoke($resolver, 123))->toBe('123');
});

it('covers remaining lines in ConfigChainRepository', function () {
    $repo = new ConfigChainRepository;
    config(['llm-router.tiers.small' => [['provider' => 'test', 'model' => 'test']]]);
    
    expect($repo->getChain('non-existent', 'non-existent'))->toBeArray()
        ->and($repo->getChain('non-existent', 'non-existent'))->toHaveCount(1);
});

it('covers remaining lines in FailoverRunner', function () {
    $classifier = new FailureClassifier;
    $runner = new FailoverRunner($classifier);
    
    // Line 30: empty chain
    expect(fn() => $runner->run([], fn() => ''))->toThrow(\InvalidArgumentException::class);
    
    // Line 150-157: getProviderLabel
    $ref = new \ReflectionClass($runner);
    $method = $ref->getMethod('getProviderLabel');
    
    $objWithValue = new class { public function value(): string { return 'val'; } };
    $objWithValueInt = new class { public function value(): int { return 123; } };
    
    expect($method->invoke($runner, $objWithValue))->toBe('val')
        ->and($method->invoke($runner, $objWithValueInt))->toBe('123')
        ->and($method->invoke($runner, 123))->toBe('123');
        
    // Line 168: logging disabled
    config(['llm-router.logging.enabled' => false]);
    $methodLog = $ref->getMethod('log');
    $methodLog->invoke($runner, 'info', 'msg', [], [], hrtime(true)); // Should just return
    expect(true)->toBe(true);
});

it('covers remaining lines in LLMRouterManager', function () {
    $manager = app(LLMRouterManager::class);
    $manager->resolveAgentTierUsing(fn() => null);
    
    expect($manager->resolveTierForAgent('SomeAgent'))->toBe(\IllumaLaw\LlmRouter\Enums\DefaultTier::Small);
});

it('covers remaining lines in PendingLlmRequest', function () {
    if (!class_exists('Laravel\\Ai\\Ai')) {
        $this->markTestSkipped('Laravel AI SDK not installed.');
    }

    $mockResponse = new TextResponse('Hello', new Usage(0, 0, 0), new Meta('openai', 'gpt-4o'));
    \Laravel\Ai\AnonymousAgent::fake(fn () => $mockResponse);

    $manager = app(LLMRouterManager::class);
    $request = new PendingLlmRequest($manager);
    
    // Mock the resolver to return a simple chain
    $manager->resolveChainUsing(fn() => [['provider' => 'openai', 'model' => 'gpt-4o']]);
    
    $result = $request->run(); // null closure
    expect($result)->toBeArray()
        ->and($result['result'])->toBeInstanceOf(TextResponse::class);
        
    \Laravel\Ai\AnonymousAgent::fake([]);
});

it('covers remaining lines in FailureClassifier', function () {
    $classifier = new FailureClassifier;
    
    // Line 29: 5xx regex
    expect($classifier->isRetryableOnSameProvider(new \Exception('Error 500')))->toBeTrue();
    
    // Line 42: keywords
    expect($classifier->isRetryableOnSameProvider(new \Exception('Internal Server Error')))->toBeTrue();
});

it('covers remaining lines in ProviderNormalizer', function () {
    expect(ProviderNormalizer::normalize(123))->toBe(123);
});

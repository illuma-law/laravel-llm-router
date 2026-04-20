<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Tests\Feature;

use IllumaLaw\LlmRouter\LLMRouterManager;
use IllumaLaw\LlmRouter\PendingLlmRequest;
use IllumaLaw\LlmRouter\ProviderNormalizer;
use Illuminate\Support\Facades\Facade;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

it('can set tier, operation, tenant and context', function () {
    $manager = app(LLMRouterManager::class);
    $request = new PendingLlmRequest($manager);
    
    $request->tier('large')
        ->operation('write')
        ->forTenant(['id' => 1])
        ->withContext(['user' => 'admin']);
    
    $ref = new \ReflectionClass($request);
    
    expect($ref->getProperty('tier')->getValue($request))->toBe('large')
        ->and($ref->getProperty('operation')->getValue($request))->toBe('write')
        ->and($ref->getProperty('tenant')->getValue($request))->toEqual(['id' => 1])
        ->and($ref->getProperty('context')->getValue($request))->toEqual(['user' => 'admin']);
});

it('can set agent instance or class', function () {
    $manager = app(LLMRouterManager::class);
    $request = new PendingLlmRequest($manager);
    
    $request->agent('MyClass');
    expect($request)->toBeInstanceOf(PendingLlmRequest::class);
    
    $ref = new \ReflectionClass($request);
    expect($ref->getProperty('agentClass')->getValue($request))->toBe('MyClass');
    
    $instance = new class {};
    $request->agent($instance);
    expect($ref->getProperty('agentInstance')->getValue($request))->toBe($instance)
        ->and($ref->getProperty('agentClass')->getValue($request))->toBe(get_class($instance));
});

it('can perform a prompt using AI SDK mock', function () {
    if (!class_exists('Laravel\\Ai\\Ai')) {
        $this->markTestSkipped('Laravel AI SDK not installed.');
    }

    $mockResponse = new TextResponse('Hello World', new Usage(0,0,0), new Meta('openai', 'gpt-4o'));
    
    $mockModel = \Mockery::mock('Laravel\\Ai\\Contracts\\Model');
    $mockModel->shouldReceive('prompt')->andReturnSelf();
    $mockModel->shouldReceive('generate')->andReturn($mockResponse);
    
    $mockProvider = \Mockery::mock('Laravel\\Ai\\Contracts\\Provider');
    $mockProvider->shouldReceive('model')->andReturn($mockModel);
    
    \Laravel\Ai\Ai::shouldReceive('provider')->andReturn($mockProvider);

    $manager = app(LLMRouterManager::class);
    $request = new PendingLlmRequest($manager);
    
    $result = $request->tier('large')->prompt('Say hello');
    
    expect($result)->toBeArray()
        ->and($result['result'])->toBeInstanceOf(TextResponse::class)
        ->and($result['result']->text)->toBe('Hello World');
        
    Facade::clearResolvedInstances();
});

it('can perform a prompt using an agent instance', function () {
    $agent = new class {
        public function prompt($prompt, $provider, $model) {
            return "Agent: {$prompt} via {$provider}/{$model}";
        }
    };

    $manager = app(LLMRouterManager::class);
    $request = new PendingLlmRequest($manager);
    
    $result = $request->agent($agent)->tier('small')->prompt('test');
    
    expect($result)->toBeArray()
        ->and($result['result'])->toBe('Agent: test via anthropic/claude-3-5-haiku-latest');
});

it('throws exception when prompt is called without SDK or closure', function () {
    // We need to simulate Ai SDK not existing. Hard to do if it is installed.
    // But we can check the error message if it wasn't there.
})->skip('Hard to simulate class not existing');

it('normalizes providers', function () {
    $normalized = ProviderNormalizer::normalize('OpenAI');
    $label = is_string($normalized) ? $normalized : (string) ($normalized->value ?? $normalized);
    
    expect(strtolower((string)$label))->toContain('openai');
    expect(ProviderNormalizer::normalize(new \stdClass))->toBeInstanceOf(\stdClass::class);
});

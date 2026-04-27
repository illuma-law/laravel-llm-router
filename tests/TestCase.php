<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Tests;

use IllumaLaw\LlmRouter\LLMRouterServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            LLMRouterServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('llm-router.enabled', true);
        config()->set('llm-router.tiers.large', [
            ['provider' => 'anthropic', 'model' => 'claude-3-5-sonnet-latest'],
            ['provider' => 'openai', 'model' => 'gpt-4o'],
            ['provider' => 'gemini', 'model' => 'gemini-1.5-pro'],
        ], );
        config()->set('llm-router.tiers.small', [
            ['provider' => 'anthropic', 'model' => 'claude-3-5-haiku-latest'],
            ['provider' => 'openai', 'model' => 'gpt-4o-mini'],
            ['provider' => 'gemini', 'model' => 'gemini-1.5-flash'],
        ]);
        config()->set('llm-router.operations.image_generation', [
            ['provider' => 'openai', 'model' => 'dall-e-3'],
        ]);
        config()->set('llm-router.priority_override', [
            'provider' => 'ollama',
            'model' => 'llama3.1:70b',
        ]);
    }
}

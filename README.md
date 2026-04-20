# Laravel LLM Router

[![Latest Version on Packagist](https://img.shields.io/packagist/v/illuma-law/laravel-llm-router.svg?style=flat-square)](https://packagist.org/packages/illuma-law/laravel-llm-router)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/illuma-law/laravel-llm-router/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/illuma-law/laravel-llm-router/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/illuma-law/laravel-llm-router.svg?style=flat-square)](https://packagist.org/packages/illuma-law/laravel-llm-router)

A production-grade, highly resilient LLM routing and failover system for Laravel. This package provides a fluent API to execute LLM requests with automatic circuit-breaking, multi-provider fallback chains, and tenant-aware routing (including data sovereignty support).

## TL;DR

```php
use IllumaLaw\LlmRouter\Facades\LLMRouter;
use IllumaLaw\LlmRouter\Enums\DefaultTier;

// Execute a prompt with automatic failover across multiple providers
$response = LLMRouter::tier(DefaultTier::Large)->prompt('Explain quantum computing.');

echo $response->text;
```

## Features

- **Fluent Request API**: Execute LLM calls with a clean, builder-like syntax.
- **Resilient Failover**: Automatically attempt alternative providers/models when primary ones fail.
- **Smart Retries**: Distinguishes between transient network errors (retry same provider) and rate limits/exhaustion (failover to next provider).
- **Tenant Isolation**: Support for "Sovereign" tenants where requests must stay on-premise (e.g., via Ollama).
- **Extensible Tiers**: Define your own model tiers (Small, Large, or custom) using Enums.
- **Chain Repository Abstraction**: Abstract your chain resolution logic (database, settings, etc.) behind a simple interface.
- **Seamless Laravel AI SDK Integration**: First-class support for `laravel/ai` agents and prompting.
- **Observability**: Detailed logging of every attempt, failure, and failover event.

## Installation

```bash
composer require illuma-law/laravel-llm-router
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="llm-router-config"
```

## Core Concepts

### 1. The Routing Chain
A chain is an ordered list of `provider` and `model` pairs. When you run a request, the router attempts the first pair. If it fails with a retryable exception, it logs the warning and moves to the next pair in the chain.

### 2. Tiers
Tiers allow you to group model configurations by capability or cost. The package comes with a `DefaultTier` enum containing `Small` and `Large`.

### 3. Chain Repository
The router resolves chains from a `ChainRepository`. By default, it uses a `ConfigChainRepository` that reads from your config file. You can swap this for a database-backed repository easily.

---

## Configuration

The `config/llm-router.php` file is your control center:

```php
return [
    'enabled' => env('LLM_ROUTER_ENABLED', true),

    'tiers' => [
        'large' => [
            ['provider' => 'anthropic', 'model' => 'claude-3-5-sonnet-latest'],
            ['provider' => 'openai', 'model' => 'gpt-4o'],
            ['provider' => 'gemini', 'model' => 'gemini-1.5-pro'],
        ],
        'small' => [
            ['provider' => 'anthropic', 'model' => 'claude-3-5-haiku-latest'],
            ['provider' => 'openai', 'model' => 'gpt-4o-mini'],
            ['provider' => 'gemini', 'model' => 'gemini-1.5-flash'],
        ],
    ],

    'priority_override' => [
        'provider' => 'ollama',
        'model' => 'llama3.1:70b',
    ],

    'max_same_provider_retries' => 1,
    'retry_delay_ms' => 150,
];
```

---

## Usage

### Seamless Laravel AI SDK Integration

The router is designed to work perfectly with the official `laravel/ai` SDK.

#### Simple Prompting
You can execute a simple prompt across a failover chain with one line:

```php
use IllumaLaw\LlmRouter\Facades\LLMRouter;
use IllumaLaw\LlmRouter\Enums\DefaultTier;

$response = LLMRouter::tier(DefaultTier::Large)->prompt('Explain quantum computing.');

echo $response->text;
```

#### Agent Integration
You can pass a `laravel/ai` agent instance directly:

```php
$agent = new LegalAnalystAgent();

$response = LLMRouter::forAgent($agent)->prompt('Analyze this contract...');
```

### Custom Execution Closure

If you need more control, use the `run()` method with a closure:

```php
$result = LLMRouter::tier(DefaultTier::Large)
    ->run(function ($provider, $model) use ($prompt) {
        // This closure is executed for each hop in the chain until success
        return Laravel\Ai::provider($provider)->model($model)->prompt($prompt)->generate();
    });
```

### Priority & Tenant Overrides

For enterprise applications, some customers might require their data to never leave their infrastructure. Register a "priority resolver":

```php
// AppServiceProvider.php
LLMRouter::resolvePriorityUsing(function ($tenant) {
    return $tenant->is_sovereign;
});

// Usage:
$result = LLMRouter::tier(DefaultTier::Large)
    ->forTenant($team) // Automatically switches to local models if $team is sovereign
    ->prompt('...');
```

---

## Advanced: Abstracting Chain Resolution

If your application stores fallback chains in a database or complex settings system (e.g., using `spatie/laravel-settings`), implement the `ChainRepository` interface.

### 1. Create your Repository

```php
use IllumaLaw\LlmRouter\Contracts\ChainRepository;

class DatabaseChainRepository implements ChainRepository
{
    public function getChain(?string $tier = null, ?string $operation = null): ?array
    {
        // Fetch from DB...
    }

    public function getAgentOverride(string $agent): ?array
    {
        // Fetch agent-specific primary model from DB...
    }
}
```

### 2. Register it in your Service Provider

```php
// AppServiceProvider.php
LLMRouter::useRepository(new DatabaseChainRepository());
```

The router will now automatically use your repository to resolve chains, including agent-specific primary model overrides, and merge them with the configured fallback logic.

---

## Error Handling

The router handles classification of errors automatically.

- **Transient Errors** (503, Timeouts, Network): Retries the *same* provider based on `max_same_provider_retries`.
- **Exhaustion Errors** (429 Rate Limits): Immediately fails over to the *next* provider in the chain.
- **Terminal Errors** (401 Unauthorized, 400 Bad Request): Fails fast and propagates the exception.

### Catching Failures

If the entire chain is exhausted, a `ChainExhaustedException` is thrown:

```php
use IllumaLaw\LlmRouter\Exceptions\ChainExhaustedException;

try {
    $result = LLMRouter::tier(DefaultTier::Large)->prompt('...');
} catch (ChainExhaustedException $e) {
    $attempts = $e->getAttempts(); // History of all failed attempts
}
```

---

## Testing

The package is built with Pest PHP and provides 100% coverage of the routing logic.

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

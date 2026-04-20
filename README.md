# Laravel LLM Router

[![Latest Version on Packagist](https://img.shields.io/packagist/v/illuma-law/laravel-llm-router.svg?style=flat-square)](https://packagist.org/packages/illuma-law/laravel-llm-router)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/illuma-law/laravel-llm-router/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/illuma-law/laravel-llm-router/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/illuma-law/laravel-llm-router.svg?style=flat-square)](https://packagist.org/packages/illuma-law/laravel-llm-router)

A production-grade, highly resilient LLM routing and failover system for Laravel applications. 

When building AI-powered features, relying on a single API provider (like OpenAI or Anthropic) introduces significant risk due to rate limits and outages. The Laravel LLM Router solves this by providing a fluent API to execute LLM requests with automatic circuit-breaking, multi-provider fallback chains, smart error classification, and tenant-aware routing.

## Features

- **Fluent Request API**: Execute LLM calls with a clean, builder-like syntax using the `LLMRouter` facade.
- **Resilient Failover**: Automatically attempt alternative providers/models when primary ones fail (e.g., fallback from OpenAI to Anthropic to Google).
- **Smart Retries**: Intelligently distinguishes between transient network errors (retry the same provider) and rate limits (failover immediately to the next provider).
- **Tenant Isolation**: Support for "Sovereign" tenants where requests must stay on-premise (e.g., forcing Ollama models).
- **Seamless Laravel AI SDK Integration**: First-class support for `laravel/ai` agents and simple prompting.
- **Extensible Configuration**: Define your own model tiers (Small, Large, etc.) via Enums, and load chains via config or database.
- **Reusable Support Primitives**: Generic provider availability checks, chain row validation, and cooldown state tracking can be reused in host apps without domain coupling.

## Support Utilities

The package now ships generic support classes for host applications that want to keep AI routing concerns out of domain code:

- `IllumaLaw\LlmRouter\Support\ConfigProviderAvailability`
  - Checks provider credentials and enablement flags using configurable config paths.
- `IllumaLaw\LlmRouter\Support\ChainRowValidator`
  - Normalizes provider/model rows, validates provider/model combinations, and removes adjacent duplicates.
- `IllumaLaw\LlmRouter\Support\CooldownStore`
  - Tracks retryable failure counters and cooldown windows in cache.
- `IllumaLaw\LlmRouter\Contracts\ProviderAvailability`
  - Contract for provider availability checks, bound to `ConfigProviderAvailability` by default.

These are container-registered by `LLMRouterServiceProvider`, so they can be injected directly or wrapped by app-specific adapters.

## Installation

Require this package with composer:

```bash
composer require illuma-law/laravel-llm-router
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="llm-router-config"
```

## Configuration

The `config/llm-router.php` file defines your fallback tiers and retry behavior:

```php
return [
    'enabled' => env('LLM_ROUTER_ENABLED', true),

    // Define chains of providers to try in order
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

    // Override model for on-prem/sovereign routing
    'priority_override' => [
        'provider' => 'ollama',
        'model' => 'llama3.1:70b',
    ],

    // How many times to retry the same provider on a 5xx error
    'max_same_provider_retries' => 1,
    'retry_delay_ms' => 150,
];
```

## Usage & Integration

### Seamless Laravel AI SDK Integration

The router is designed to work perfectly with the official `laravel/ai` SDK.

#### Simple Prompting

You can execute a simple prompt across a failover chain with one line. If Claude fails, it will automatically try OpenAI:

```php
use IllumaLaw\LlmRouter\Facades\LLMRouter;
use IllumaLaw\LlmRouter\Enums\DefaultTier;

$response = LLMRouter::tier(DefaultTier::Large)->prompt('Explain quantum computing.');

echo $response->text;
```

#### Agent Integration

You can pass a `laravel/ai` agent instance directly to run its specific logic through the router's failover chain:

```php
use App\Agents\LegalAnalystAgent;
use IllumaLaw\LlmRouter\Facades\LLMRouter;
use IllumaLaw\LlmRouter\Enums\DefaultTier;

$agent = new LegalAnalystAgent();

$response = LLMRouter::forAgent($agent)
    ->tier(DefaultTier::Large)
    ->prompt('Analyze this contract...', ['context' => '...']);
```

### Custom Execution Closure

If you need full control over the execution, use the `run()` method with a closure:

```php
use IllumaLaw\LlmRouter\Facades\LLMRouter;
use IllumaLaw\LlmRouter\Enums\DefaultTier;
use Laravel\Ai\Facades\Ai;

$result = LLMRouter::tier(DefaultTier::Large)
    ->run(function (string $provider, string $model) use ($prompt) {
        // This closure is executed for each hop in the chain until success
        return Ai::provider($provider)
            ->model($model)
            ->prompt($prompt)
            ->generate();
    });
```

### Priority & Tenant Overrides

For enterprise applications, some customers might require their data to never leave their infrastructure. You can register a "priority resolver" in your `AppServiceProvider`:

```php
use IllumaLaw\LlmRouter\Facades\LLMRouter;

// AppServiceProvider.php
public function boot(): void
{
    LLMRouter::resolvePriorityUsing(function ($tenant) {
        // If the tenant is flagged as sovereign, trigger the priority_override (e.g. Ollama)
        return $tenant->is_sovereign === true;
    });
}
```

Then pass the tenant context to the router:

```php
$result = LLMRouter::tier(DefaultTier::Large)
    ->forTenant($team) // Automatically switches to local models if $team is sovereign
    ->prompt('Summarize this document.');
```

### Abstracting Chain Resolution

If your application stores fallback chains dynamically in a database, you can implement the `ChainRepository` interface.

```php
use IllumaLaw\LlmRouter\Contracts\ChainRepository;

class DatabaseChainRepository implements ChainRepository
{
    public function getChain(?string $tier = null, ?string $operation = null): ?array
    {
        return DB::table('ai_chains')->where('tier', $tier)->get()->toArray();
    }

    public function getAgentOverride(string $agent): ?array
    {
        return null;
    }
}
```

Register it in your Service Provider:

```php
LLMRouter::useRepository(new DatabaseChainRepository());
```

## Error Handling

The router automatically classifies errors:

- **Transient Errors** (503, Timeouts, Network): Retries the *same* provider.
- **Exhaustion Errors** (429 Rate Limits): Immediately fails over to the *next* provider in the chain.
- **Terminal Errors** (401 Unauthorized, 400 Bad Request): Fails fast and propagates the exception without retrying.

If the entire chain is exhausted, a `ChainExhaustedException` is thrown:

```php
use IllumaLaw\LlmRouter\Exceptions\ChainExhaustedException;
use IllumaLaw\LlmRouter\Facades\LLMRouter;

try {
    $result = LLMRouter::tier('large')->prompt('...');
} catch (ChainExhaustedException $e) {
    // Inspect what went wrong at each step
    $attempts = $e->getAttempts(); 
    Log::critical('All AI providers failed.', ['history' => $attempts]);
}
```

## Testing

The package includes a comprehensive test suite using Pest PHP.

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

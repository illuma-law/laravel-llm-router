---
description: Resilient LLM routing and failover for Laravel — fluent request API, tenant isolation, chain resolution
---

# laravel-llm-router

Resilient LLM routing and failover system for Laravel. Routes AI agent prompts through prioritised provider/model chains with automatic retries and tenant-aware overrides.

## Namespace

`IllumaLaw\LlmRouter`

## Key Classes & Facades

- `LLMRouter` facade — primary entry point
- `FailoverRunner` — executes the chain with retries, injectable via `app(FailoverRunner::class)`
- `ProviderNormalizer::normalize($provider)` — normalises provider strings/enums to `Lab|string|null`
- `DefaultTier` enum — `Standard`, `Extended`

## Fluent Agent API (preferred for simple calls)

```php
use IllumaLaw\LlmRouter\Facades\LLMRouter;

$ran = LLMRouter::forAgent(MyAgent::class)
    ->withContext(['operation' => 'my_op', 'profile' => 'marketing'])
    ->run(fn ($provider, $model) => (new MyAgent)->prompt(
        prompt: $userPrompt,
        provider: $provider,
        model: $model,
    ));
// $ran['result'], $ran['provider'], $ran['model']
```

## Chain Resolution

```php
// Resolve a failover chain (list of provider/model hops)
$chain = LLMRouter::resolve(
    tier: 'small',      // 'small'|'large'|null
    operation: 'my_op', // maps to config ai.fallback.operations.*
    tenant: $team,      // ?Team for tenant-scoped overrides
    agent: MyAgent::class,
);

// Resolve the configured tier for an agent
$tier = LLMRouter::resolveTierForAgent(MyAgent::class); // 'small'|'large'|null

// Register a custom tier resolver (done in service providers)
LLMRouter::resolveAgentTierUsing(fn (string $agentClass) => 'small');
```

## FailoverRunner (low-level)

```php
use IllumaLaw\LlmRouter\FailoverRunner;

$result = app(FailoverRunner::class)->run(
    $chain,                                              // list<array{provider, model}>
    fn ($provider, $model) => myAgentCall($provider, $model),
    ['agent_class' => MyAgent::class, 'operation' => 'x'],
);
// returns array{result, provider, model, provider_label}
```

## App-Layer Chain Builder

The app wraps chain resolution in `App\Support\Ai\AgentChainResolver::failoverChain()` which handles tier mapping (`'standard'` → `'small'`, `'extended'` → `'large'`), primary-hop deduplication, and provider normalisation. Prefer `AgentChainResolver` over calling `LLMRouter::resolve()` directly in ingestion/action classes.

## Testing

```php
use IllumaLaw\LlmRouter\Facades\LLMRouter;

LLMRouter::fake(); // records calls, no real HTTP
LLMRouter::assertForAgentCalled(MyAgent::class);
```

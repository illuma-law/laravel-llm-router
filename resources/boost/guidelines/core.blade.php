# illuma-law/laravel-llm-router

Resilient LLM routing and failover system. Supports automatic failover, circuit breaking, and tenant-aware routing.

## Usage

### Simple Prompting
Executes across a failover chain (e.g., Claude -> GPT-4).

```php
use IllumaLaw\LlmRouter\Facades\LLMRouter;
use IllumaLaw\LlmRouter\Enums\DefaultTier;

$response = LLMRouter::tier(DefaultTier::Large)->prompt('Explain quantum computing.');
```

### Agent Integration
Run `laravel/ai` agents through the failover chain.

```php
$agent = new LegalAnalystAgent();
$response = LLMRouter::forAgent($agent)
    ->tier(DefaultTier::Large)
    ->prompt('Analyze this...', ['context' => '...']);
```

### Tenant Overrides
Force specific providers (e.g., Ollama) for sovereign tenants.

```php
LLMRouter::resolvePriorityUsing(fn ($tenant) => $tenant->is_sovereign);

$result = LLMRouter::tier(DefaultTier::Large)
    ->forTenant($team)
    ->prompt('Summarize...');
```

## Configuration

Publish config: `php artisan vendor:publish --tag="llm-router-config"`

Define fallback tiers in `config/llm-router.php`:
```php
'tiers' => [
    'large' => [
        ['provider' => 'anthropic', 'model' => 'claude-3-5-sonnet-latest'],
        ['provider' => 'openai', 'model' => 'gpt-4o'],
    ],
],
```

## Error Handling

- **Transient (5xx/Timeout)**: Retries same provider.
- **Exhaustion (429)**: Fails over to next provider.
- **Terminal (401/400)**: Fails fast.
Throws `ChainExhaustedException` if all fail.

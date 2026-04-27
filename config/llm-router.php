<?php

declare(strict_types=1);
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

return [
    /*
    |--------------------------------------------------------------------------
    | LLM Router Enabled
    |--------------------------------------------------------------------------
    |
    | When enabled, the router will automatically failover to the next model
    | in the chain if a request fails with a retryable exception.
    |
    */
    'enabled' => env('LLM_ROUTER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | AI Fallback Tiers
    |--------------------------------------------------------------------------
    |
    | Define the ordered chains for different tiers. Each item in the chain
    | must specify the 'provider' and 'model'.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Custom Operation Chains
    |--------------------------------------------------------------------------
    |
    | You can define custom chains for specific operations. These will take
    | precedence over the tier-based chains.
    |
    */
    'operations' => [
        'image_generation' => [
            ['provider' => 'openai', 'model' => 'dall-e-3'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Priority Override
    |--------------------------------------------------------------------------
    |
    | Configuration for forcing specific models when a tenant/context is
    | identified as requiring prioritized routing.
    |
    */
    'priority_override' => [
        'provider' => env('LLM_ROUTER_PRIORITY_PROVIDER', 'ollama'),
        'model' => env('LLM_ROUTER_PRIORITY_MODEL', 'llama3.1:70b'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retryable Exceptions
    |--------------------------------------------------------------------------
    |
    | A list of exception classes that should trigger a failover attempt.
    |
    */
    'retryable_exceptions' => [
        ConnectionException::class,
        RequestException::class, // Will check for 429/50x in FailoverRunner
        RuntimeException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how many times the router should retry the SAME provider
    | before falling back to the next one in the chain.
    |
    */
    'max_same_provider_retries' => env('LLM_ROUTER_MAX_RETRIES', 1),
    'retry_delay_ms' => env('LLM_ROUTER_RETRY_DELAY', 100),

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    |
    | Toggle logging for failover attempts and exhausted chains.
    |
    */
    'logging' => [
        'enabled' => env('LLM_ROUTER_LOGGING_ENABLED', true),
        'channel' => env('LLM_ROUTER_LOG_CHANNEL', 'stack'),
    ],
];

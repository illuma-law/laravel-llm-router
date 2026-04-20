<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Facades;

use IllumaLaw\LlmRouter\LLMRouterManager;
use IllumaLaw\LlmRouter\PendingLlmRequest;
use Illuminate\Support\Facades\Facade;

/**
 * @method static LLMRouterManager resolvePriorityUsing(\Closure $resolver)
 * @method static LLMRouterManager resolveAgentTierUsing(\Closure $resolver)
 * @method static LLMRouterManager resolveChainUsing(\Closure $resolver)
 * @method static bool hasPriority(mixed $tenant)
 * @method static \IllumaLaw\LlmRouter\Contracts\AiTier|string resolveTierForAgent(string $agentClass)
 * @method static list<array{provider: string, model: string}> resolve(\IllumaLaw\LlmRouter\Contracts\AiTier|string|null $tier = null, ?string $operation = null, mixed $tenant = null, ?string $agent = null)
 * @method static PendingLlmRequest tier(\IllumaLaw\LlmRouter\Contracts\AiTier|string $tier)
 * @method static PendingLlmRequest forAgent(string $agentClass)
 * @method static PendingLlmRequest operation(string $operation)
 *
 * @see LLMRouterManager
 */
class LLMRouter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'llm-router';
    }
}

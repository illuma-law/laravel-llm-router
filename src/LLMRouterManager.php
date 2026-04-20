<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter;

use Closure;
use IllumaLaw\LlmRouter\Contracts\AiTier;
use IllumaLaw\LlmRouter\Enums\DefaultTier;
use Illuminate\Contracts\Foundation\Application;

class LLMRouterManager
{
    protected ?Closure $priorityResolver = null;

    protected ?Closure $agentTierResolver = null;

    public function __construct(protected Application $app) {}

    public function resolvePriorityUsing(Closure $resolver): self
    {
        $this->priorityResolver = $resolver;

        return $this;
    }

    public function resolveAgentTierUsing(Closure $resolver): self
    {
        $this->agentTierResolver = $resolver;

        return $this;
    }

    public function useRepository(Contracts\ChainRepository $repository): self
    {
        $this->getResolver()->setRepository($repository);

        return $this;
    }

    public function resolveChainUsing(Closure $resolver): self
    {
        $this->getResolver()->resolveUsing($resolver);

        return $this;
    }

    public function hasPriority(mixed $tenant): bool
    {
        if ($this->priorityResolver === null) {
            return false;
        }

        return (bool) ($this->priorityResolver)($tenant);
    }

    public function resolveTierForAgent(string $agentClass): AiTier|string
    {
        if ($this->agentTierResolver === null) {
            return DefaultTier::Small;
        }

        $tier = ($this->agentTierResolver)($agentClass);

        if ($tier instanceof AiTier || is_string($tier)) {
            return $tier;
        }

        return DefaultTier::Small;
    }

    public function tier(AiTier|string $tier): PendingLlmRequest
    {
        return (new PendingLlmRequest($this))->tier($tier);
    }

    public function forAgent(string $agentClass): PendingLlmRequest
    {
        $tier = $this->resolveTierForAgent($agentClass);

        return (new PendingLlmRequest($this))
            ->tier($tier)
            ->agent($agentClass)
            ->withContext(['agent_class' => $agentClass]);
    }

    public function operation(string $operation): PendingLlmRequest
    {
        return (new PendingLlmRequest($this))->operation($operation);
    }

    /**
     * @return list<array{provider: string, model: string}>
     */
    public function resolve(AiTier|string|null $tier = null, ?string $operation = null, mixed $tenant = null, ?string $agent = null): array
    {
        return $this->getResolver()->resolve($tier, $operation, $tenant, $agent);
    }

    public function getResolver(): ChainResolver
    {
        return $this->app->make(ChainResolver::class);
    }

    public function getRunner(): FailoverRunner
    {
        return $this->app->make(FailoverRunner::class);
    }
}

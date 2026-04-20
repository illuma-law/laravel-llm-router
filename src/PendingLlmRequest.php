<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter;

use Closure;
use IllumaLaw\LlmRouter\Contracts\AiTier;

class PendingLlmRequest
{
    protected AiTier|string|null $tier = null;

    protected ?string $operation = null;

    protected mixed $tenant = null;

    protected ?string $agentClass = null;

    protected mixed $agentInstance = null;

    /** @var array<string, mixed> */
    protected array $context = [];

    public function __construct(protected LLMRouterManager $manager) {}

    public function tier(AiTier|string $tier): self
    {
        $this->tier = $tier;

        return $this;
    }

    public function operation(string $operation): self
    {
        $this->operation = $operation;

        return $this;
    }

    public function forTenant(mixed $tenant): self
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function agent(mixed $agent): self
    {
        if (is_string($agent)) {
            $this->agentClass = $agent;
        } elseif (is_object($agent)) {
            $this->agentInstance = $agent;
            $this->agentClass = get_class($agent);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    public function prompt(string $prompt): mixed
    {
        return $this->run(function (string $provider, string $model) use ($prompt) {
            $instance = $this->agentInstance;
            if (is_object($instance) && method_exists($instance, 'prompt')) {
                /** @var mixed $result */
                $result = $instance->prompt($prompt, provider: $provider, model: $model);

                return $result;
            }

            if (class_exists('Laravel\\Ai\\Ai')) {
                /** @var \Laravel\Ai\Contracts\Agent $agent */
                $agent = \Laravel\Ai\agent();

                return $agent->prompt($prompt, provider: $provider, model: $model);
            }

            throw new \RuntimeException('Laravel AI SDK not found. Please provide a closure to run().');
        });
    }

    /**
     * @template T
     *
     * @param  Closure(string $provider, string $model): T|null  $closure
     * @return T|array{result: mixed, provider: string, model: string, provider_label: string}
     */
    public function run(?Closure $closure = null): mixed
    {
        if ($closure === null) {
            /** @var T|array{result: mixed, provider: string, model: string, provider_label: string} $result */
            $result = $this->prompt('');

            return $result;
        }

        $chain = $this->manager->getResolver()->resolve(
            $this->tier,
            $this->operation,
            $this->tenant,
            $this->agentClass
        );

        return $this->manager->getRunner()->run($chain, $closure, $this->context);
    }
}

<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter;

use Closure;
use IllumaLaw\LlmRouter\Contracts\AiTier;
use IllumaLaw\LlmRouter\Contracts\ChainRepository;
use Illuminate\Support\Facades\Config;

class ChainResolver
{
    protected ?Closure $chainResolver = null;

    protected ?ChainRepository $repository = null;

    public function __construct(protected LLMRouterManager $manager) {}

    public function resolveUsing(Closure $resolver): self
    {
        $this->chainResolver = $resolver;

        return $this;
    }

    public function setRepository(ChainRepository $repository): self
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * @return list<array{provider: string, model: string}>
     */
    public function resolve(AiTier|string|null $tier = null, ?string $operation = null, mixed $tenant = null, ?string $agent = null): array
    {
        $tierValue = $this->getTierValue($tier);

        if ($tenant !== null && $this->manager->hasPriority($tenant)) {
            $override = Config::get('llm-router.priority_override');
            if (is_array($override) && isset($override['provider'])) {
                /** @var mixed $model */
                $model = $override['model'];

                return [
                    [
                        'provider' => $this->getProviderValue($override['provider']),
                        'model' => is_string($model) ? $model : (is_scalar($model) ? (string) $model : ''),
                    ],
                ];
            }
        }

        if ($this->chainResolver !== null) {
            /** @var list<array<string, mixed>>|null $chain */
            $chain = ($this->chainResolver)($tierValue, $operation, $tenant, $agent);

            if ($chain !== null) {
                return array_map(function (array $item): array {
                    /** @var mixed $model */
                    $model = $item['model'] ?? '';

                    return [
                        'provider' => $this->getProviderValue($item['provider'] ?? ''),
                        'model' => is_string($model) ? $model : (is_scalar($model) ? (string) $model : ''),
                    ];
                }, $chain);
            }
        }

        $finalChain = [];
        $repository = $this->repository ?? new ConfigChainRepository;

        if ($agent !== null && $agent !== '') {
            $override = $repository->getAgentOverride($agent);
            if (is_array($override) && isset($override['provider'])) {
                /** @var mixed $model */
                $model = $override['model'];

                $finalChain[] = [
                    'provider' => $this->getProviderValue($override['provider']),
                    'model' => is_string($model) ? $model : (is_scalar($model) ? (string) $model : ''),
                ];
            }
        }

        /** @var list<array<string, mixed>> $baseChain */
        $baseChain = $repository->getChain($tierValue, $operation) ?? [];

        foreach ($baseChain as $item) {
            /** @var mixed $model */
            $model = $item['model'] ?? '';

            $normalizedItem = [
                'provider' => $this->getProviderValue($item['provider'] ?? ''),
                'model' => is_string($model) ? $model : (is_scalar($model) ? (string) $model : ''),
            ];

            if ($this->isDuplicate($normalizedItem, $finalChain)) {
                continue;
            }
            $finalChain[] = $normalizedItem;
        }

        if (empty($finalChain)) {
            /** @var list<array<string, mixed>> $defaultChain */
            $defaultChain = Config::get('llm-router.tiers.small', []);

            return array_map(function (array $item): array {
                /** @var mixed $model */
                $model = $item['model'] ?? '';

                return [
                    'provider' => $this->getProviderValue($item['provider'] ?? ''),
                    'model' => is_string($model) ? $model : (is_scalar($model) ? (string) $model : ''),
                ];
            }, $defaultChain);
        }

        return $finalChain;
    }

    /**
     * @param  array{provider: string, model: string}  $item
     * @param  list<array{provider: string, model: string}>  $chain
     */
    protected function isDuplicate(array $item, array $chain): bool
    {
        foreach ($chain as $existing) {
            if (strtolower($item['provider']) === strtolower($existing['provider']) && $item['model'] === $existing['model']) {
                return true;
            }
        }

        return false;
    }

    protected function getProviderValue(mixed $provider): string
    {
        if ($provider instanceof \BackedEnum) {
            return (string) $provider->value;
        }

        return is_string($provider) ? $provider : (is_scalar($provider) ? (string) $provider : '');
    }

    protected function getTierValue(AiTier|string|null $tier): ?string
    {
        if ($tier === null) {
            return null;
        }

        if ($tier instanceof AiTier) {
            return $tier->value();
        }

        return $tier;
    }
}

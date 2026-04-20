<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter;

use IllumaLaw\LlmRouter\Contracts\ChainRepository;
use Illuminate\Support\Facades\Config;

class ConfigChainRepository implements ChainRepository
{
    /**
     * @return list<array{provider: mixed, model: string}>|null
     */
    public function getChain(?string $tier = null, ?string $operation = null): ?array
    {
        if ($operation !== null) {
            /** @var list<array{provider: mixed, model: string}>|null $chain */
            $chain = Config::get("llm-router.operations.{$operation}");
            if (! empty($chain)) {
                return $chain;
            }
        }

        if ($tier !== null) {
            /** @var list<array{provider: mixed, model: string}>|null $chain */
            $chain = Config::get("llm-router.tiers.{$tier}");
            if (! empty($chain)) {
                return $chain;
            }
        }

        /** @var list<array{provider: mixed, model: string}>|null $defaultChain */
        $defaultChain = Config::get('llm-router.tiers.small', []);

        return $defaultChain;
    }

    public function getAgentOverride(string $agent): ?array
    {
        return null;
    }
}

<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Contracts;

interface ChainRepository
{
    /**
     * @return list<array{provider: mixed, model: string}>|null
     */
    public function getChain(?string $tier = null, ?string $operation = null): ?array;

    /**
     * @return array{provider: mixed, model: string}|null
     */
    public function getAgentOverride(string $agent): ?array;
}

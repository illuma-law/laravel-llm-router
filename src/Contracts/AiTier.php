<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Contracts;

interface AiTier
{
    public function value(): string;
}

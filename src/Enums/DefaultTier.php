<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Enums;

use IllumaLaw\LlmRouter\Contracts\AiTier;

enum DefaultTier: string implements AiTier
{
    case Small = 'small';
    case Large = 'large';

    public function value(): string
    {
        return $this->value;
    }
}

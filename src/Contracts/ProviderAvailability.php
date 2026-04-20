<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Contracts;

interface ProviderAvailability
{
    public function hasCredentials(mixed $provider): bool;

    public function isEnabled(mixed $provider): bool;
}


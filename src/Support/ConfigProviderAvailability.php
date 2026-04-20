<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Support;

use IllumaLaw\LlmRouter\Contracts\ProviderAvailability;

final class ConfigProviderAvailability implements ProviderAvailability
{
    /**
     * @param  array<string, string>  $aliases
     */
    public function __construct(
        private readonly string $providersConfigPath = 'llm-router.providers',
        private readonly string $togglesConfigPath = 'llm-router.toggles',
        private readonly array $aliases = [],
    ) {}

    public function hasCredentials(mixed $provider): bool
    {
        $driver = $this->driverFromProviderName($provider);

        if ($driver === '') {
            return false;
        }

        $block = config("{$this->providersConfigPath}.{$driver}");

        if (! is_array($block)) {
            return false;
        }

        if ($driver === 'ollama') {
            return filled($block['url'] ?? null);
        }

        if ($driver === 'azure') {
            return filled($block['key'] ?? null) && filled($block['url'] ?? null);
        }

        return filled($block['key'] ?? null);
    }

    public function isEnabled(mixed $provider): bool
    {
        $driver = $this->driverFromProviderName($provider);

        if ($driver === '') {
            return false;
        }

        $toggle = config("{$this->togglesConfigPath}.{$driver}");

        if ($toggle === null) {
            return true;
        }

        return (bool) $toggle;
    }

    private function driverFromProviderName(mixed $provider): string
    {
        if ($provider instanceof \BackedEnum) {
            $provider = $provider->value;
        }

        if (! is_scalar($provider)) {
            return '';
        }

        $name = strtolower(trim((string) $provider));

        if ($name === '') {
            return '';
        }

        return $this->aliases[$name] ?? $name;
    }
}

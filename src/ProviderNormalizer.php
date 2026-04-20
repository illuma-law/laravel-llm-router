<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter;

class ProviderNormalizer
{
    public static function normalize(mixed $provider): mixed
    {
        $providerValue = $provider instanceof \BackedEnum ? $provider->value : $provider;

        if (! is_string($providerValue)) {
            return $provider;
        }

        $normalized = strtolower($providerValue);

        if (class_exists('Laravel\\Ai\\Enums\\Lab')) {
            /** @var class-string<\BackedEnum> $labClass */
            $labClass = 'Laravel\\Ai\\Enums\\Lab';

            try {
                return $labClass::from($normalized);
            } catch (\Throwable) {
                foreach ($labClass::cases() as $case) {
                    if (strtolower((string) $case->value) === $normalized) {
                        return $case;
                    }
                }
            }
        }

        return $normalized;
    }
}

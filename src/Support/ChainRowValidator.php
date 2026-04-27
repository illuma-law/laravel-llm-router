<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Support;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class ChainRowValidator
{
    /**
     * @param  list<string>  $allowedProviders
     */
    public function __construct(
        private readonly array $allowedProviders = ['gemini', 'openai', 'openrouter', 'ollama', 'voyageai'],
    ) {}

    /**
     * @param  array<string, list<string>>  $patternsByProvider
     * @param  array<string, list<string>>  $allowlistByProvider
     * @return list<array{provider: string, model: string, enabled: bool}>
     */
    public function normalizeRows(
        mixed $rows,
        array $patternsByProvider = [],
        array $allowlistByProvider = [],
    ): array {
        if (! is_array($rows)) {
            throw ValidationException::withMessages([
                'rows' => 'Malformed chain payload.',
            ]);
        }

        $normalized = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    "rows.{$index}" => 'Malformed chain row.',
                ]);
            }

            $rawProvider = $row['provider'] ?? '';
            $provider = is_scalar($rawProvider) ? strtolower(trim((string) $rawProvider)) : '';
            if ($provider === 'voyage') {
                $provider = 'voyageai';
            }

            $rawModel = $row['model'] ?? '';
            $model = is_scalar($rawModel) ? trim((string) $rawModel) : '';
            $enabled = (bool) ($row['enabled'] ?? false);

            if (! in_array($provider, $this->allowedProviders, true)) {
                throw ValidationException::withMessages([
                    "rows.{$index}.provider" => 'Invalid provider.',
                ]);
            }

            if ($model === '') {
                throw ValidationException::withMessages([
                    "rows.{$index}.model" => 'Model is required.',
                ]);
            }

            if (! $this->modelMatchesProviderRules($provider, $model, $patternsByProvider, $allowlistByProvider)) {
                throw ValidationException::withMessages([
                    "rows.{$index}.model" => 'Model does not match provider rules.',
                ]);
            }

            $normalized[] = [
                'provider' => $provider,
                'model'    => $model,
                'enabled'  => $enabled,
            ];
        }

        return $this->removeAdjacentDuplicates($normalized);
    }

    /**
     * @param  list<array{provider: string, model: string, enabled: bool}>  $rows
     */
    public function ensureHasEnabledRows(array $rows, string $field = 'rows'): void
    {
        $hasEnabled = collect($rows)->contains(static fn (array $row): bool => $row['enabled']);

        if ($hasEnabled) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'At least one enabled row is required.',
        ]);
    }

    /**
     * @param  list<array{provider: string, model: string, enabled: bool}>  $rows
     * @return list<array{provider: string, model: string, enabled: bool}>
     */
    private function removeAdjacentDuplicates(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $last = $out === [] ? null : $out[count($out) - 1];

            if (
                $last !== null
                && $last['provider'] === $row['provider']
                && $last['model'] === $row['model']
                && $last['enabled'] === $row['enabled']
            ) {
                continue;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, list<string>>  $patternsByProvider
     * @param  array<string, list<string>>  $allowlistByProvider
     */
    private function modelMatchesProviderRules(
        string $provider,
        string $model,
        array $patternsByProvider,
        array $allowlistByProvider,
    ): bool {
        $patterns = array_filter(array_map(
            static fn (mixed $pattern): string => is_string($pattern) ? trim($pattern) : '',
            Arr::wrap(Arr::get($patternsByProvider, $provider, [])),
        ));

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $model) === 1) {
                return true;
            }
        }

        $allowlist = array_values(array_filter(array_map(
            static fn (mixed $allowedModel): string => is_string($allowedModel) ? trim($allowedModel) : '',
            Arr::wrap(Arr::get($allowlistByProvider, $provider, [])),
        )));

        if ($allowlist !== []) {
            return in_array($model, $allowlist, true);
        }

        return false;
    }
}

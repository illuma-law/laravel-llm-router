<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Support;

use Illuminate\Support\Facades\Cache;

final class CooldownStore
{
    public function __construct(
        private readonly string $keyPrefix = 'llm-router',
    ) {}

    public function shouldSkip(string $profile, string $provider, string $model): bool
    {
        return Cache::has($this->cooldownKey($profile, $provider, $model));
    }

    public function recordSuccess(string $profile, string $provider, string $model): void
    {
        Cache::forget($this->cooldownKey($profile, $provider, $model));
        Cache::forget($this->failureCountKey($profile, $provider, $model));
    }

    public function recordRetryableFailure(
        string $profile,
        string $provider,
        string $model,
        int $threshold,
        int $cooldownSeconds,
        int $failureCounterTtlSeconds,
    ): void {
        $countKey = $this->failureCountKey($profile, $provider, $model);
        $cooldownKey = $this->cooldownKey($profile, $provider, $model);
        $failureTtl = max(1, $failureCounterTtlSeconds);
        $tripThreshold = max(1, $threshold);

        $count = Cache::increment($countKey);

        if ($count === 1) {
            Cache::put($countKey, 1, now()->addSeconds($failureTtl));
        }

        if ($count < $tripThreshold) {
            return;
        }

        Cache::put($cooldownKey, true, now()->addSeconds(max(1, $cooldownSeconds)));
        Cache::forget($countKey);
    }

    private function cooldownKey(string $profile, string $provider, string $model): string
    {
        return sprintf('%s:cooldown:%s:%s:%s', $this->keyPrefix, $profile, $provider, md5($model));
    }

    private function failureCountKey(string $profile, string $provider, string $model): string
    {
        return sprintf('%s:failures:%s:%s:%s', $this->keyPrefix, $profile, $provider, md5($model));
    }
}


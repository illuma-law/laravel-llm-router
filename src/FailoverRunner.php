<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter;

use Closure;
use IllumaLaw\LlmRouter\Exceptions\ChainExhaustedException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

class FailoverRunner
{
    public function __construct(protected FailureClassifier $classifier) {}

    /**
     * @template T
     *
     * @param  list<array{provider: string, model: string}>  $chain
     * @param  Closure(string $provider, string $model): T  $invoke
     * @param  array<string, mixed>  $context
     * @return array{result: T, provider: string, model: string, provider_label: string}
     *
     * @throws ChainExhaustedException|Throwable
     */
    public function run(array $chain, Closure $invoke, array $context = []): array
    {
        if (empty($chain)) {
            throw new \InvalidArgumentException('AI failover chain cannot be empty.');
        }

        $configEnabled = Config::get('llm-router.enabled');
        $enabled = is_bool($configEnabled) ? $configEnabled : true;
        $failedAttempts = [];
        $startTime = (int) hrtime(true);
        $failoverCount = 0;

        foreach ($chain as $index => $step) {
            $provider = $step['provider'];
            $model = $step['model'];

            $rawNormalizedProvider = ProviderNormalizer::normalize($provider);
            $normalizedProvider = $this->getProviderLabel($rawNormalizedProvider);

            $providerLabel = $this->getProviderLabel($normalizedProvider);

            $configMaxRetries = Config::get('llm-router.max_same_provider_retries');
            $maxSameProviderAttempts = is_int($configMaxRetries) ? $configMaxRetries : 1;
            $sameProviderAttempts = 0;

            do {
                try {
                    $result = $invoke($normalizedProvider, $model);

                    $this->log('info', 'ai.fallback_success', $context, [
                        'provider' => $providerLabel,
                        'model' => $model,
                        'attempt_index' => $index,
                        'same_provider_retry_index' => $sameProviderAttempts,
                        'failover_count' => $failoverCount,
                    ], $startTime);

                    return [
                        'result' => $result,
                        'provider' => $normalizedProvider,
                        'model' => $model,
                        'provider_label' => $providerLabel,
                    ];
                } catch (Throwable $e) {
                    $sameProviderAttempts++;

                    if (! $enabled || $this->classifier->isTerminal($e)) {
                        $this->logExhausted($providerLabel, $model, $e, $index, $sameProviderAttempts, $failoverCount, $context, $startTime);
                        throw $e;
                    }

                    if ($this->classifier->isRetryableOnSameProvider($e) && $sameProviderAttempts <= $maxSameProviderAttempts) {
                        $this->log('warning', 'ai.fallback_retry', $context, [
                            'provider' => $providerLabel,
                            'model' => $model,
                            'same_provider_retry_index' => $sameProviderAttempts,
                            'message' => $e->getMessage(),
                        ], $startTime);

                        $configDelay = Config::get('llm-router.retry_delay_ms');
                        $delay = is_int($configDelay) ? $configDelay : 100;
                        if ($delay > 0) {
                            usleep($delay * 1000);
                        }

                        continue;
                    }

                    $failedAttempts[] = [
                        'provider' => $provider,
                        'model' => $model,
                        'exception' => $e,
                    ];

                    $failoverCount++;

                    $this->log('warning', 'ai.fallback_attempt', $context, [
                        'provider' => $providerLabel,
                        'model' => $model,
                        'attempt_index' => $index,
                        'message' => $e->getMessage(),
                        'next_step' => ($index + 1) < count($chain) ? 'falling back' : 'exhausted',
                    ], $startTime);

                    if ($index === count($chain) - 1) {
                        $this->logExhausted($providerLabel, $model, $e, $index, $sameProviderAttempts, $failoverCount, $context, $startTime);
                        throw new ChainExhaustedException($failedAttempts);
                    }

                    break;
                }
            } while (true);
        }

        throw new ChainExhaustedException($failedAttempts);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logExhausted(string $provider, string $model, Throwable $e, int $index, int $retryIndex, int $failoverCount, array $context, int $startTime): void
    {
        $this->log('error', 'ai.fallback_exhausted', $context, [
            'provider' => $provider,
            'model' => $model,
            'attempt_index' => $index,
            'same_provider_retry_index' => $retryIndex,
            'failover_count' => $failoverCount,
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
        ], $startTime);
    }

    protected function getProviderLabel(mixed $provider): string
    {
        if (is_string($provider)) {
            return $provider;
        }

        if ($provider instanceof \BackedEnum) {
            return (string) $provider->value;
        }

        if (is_object($provider) && method_exists($provider, 'value')) {
            /** @var mixed $val */
            $val = $provider->value();

            return is_string($val) ? $val : (is_scalar($val) ? (string) $val : '');
        }

        return is_scalar($provider) ? (string) $provider : '';
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $extra
     */
    protected function log(string $level, string $message, array $context, array $extra, int $startTime): void
    {
        $configLogEnabled = Config::get('llm-router.logging.enabled');
        if (! (is_bool($configLogEnabled) ? $configLogEnabled : true)) {
            return;
        }

        $configChannel = Config::get('llm-router.logging.channel');
        $channel = is_string($configChannel) ? $configChannel : 'stack';

        $durationMs = round(((int) hrtime(true) - $startTime) / 1_000_000, 2);

        $payload = array_merge([
            'duration_ms' => $durationMs,
        ], $context, $extra);

        if ($channel === 'stack') {
            Log::{$level}($message, $payload);
        } else {
            Log::channel($channel)->{$level}($message, $payload);
        }
    }
}

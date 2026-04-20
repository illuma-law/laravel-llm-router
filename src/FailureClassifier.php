<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter;

use Throwable;

final class FailureClassifier
{
    public function isRetryableOnSameProvider(Throwable $e): bool
    {
        $message = $this->aggregateMessages($e);

        if ($this->matchesKeywords($message, [
            'timed out',
            'timeout',
            'connection refused',
            'connection reset',
            'could not resolve host',
            'curl error',
            'network is unreachable',
            'empty reply from server',
        ])) {
            return true;
        }

        if (preg_match('/\b(?:500|502|503|504)\b/', $message) === 1) {
            return true;
        }

        if ($this->matchesKeywords($message, [
            'status code 500',
            'status code 502',
            'status code 503',
            'status code 504',
            'internal server error',
            'service unavailable',
            'bad gateway',
            'gateway timeout',
        ])) {
            return true;
        }

        return false;
    }

    public function shouldFailover(Throwable $e): bool
    {
        $message = $this->aggregateMessages($e);

        if (preg_match('/\b429\b/', $message) === 1 || $this->matchesKeywords($message, [
            'status code 429',
            'rate limit',
            'quota',
            'resource exhausted',
            'too many requests',
            'insufficient_quota',
        ])) {
            return true;
        }

        return false;
    }

    public function isTerminal(Throwable $e): bool
    {
        return ! $this->isRetryableOnSameProvider($e) && ! $this->shouldFailover($e);
    }

    /**
     * @param  list<string>  $keywords
     */
    protected function matchesKeywords(string $message, array $keywords): bool
    {
        $message = strtolower($message);
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function aggregateMessages(Throwable $e): string
    {
        $parts = [];
        $current = $e;

        while ($current instanceof Throwable) {
            $msg = trim($current->getMessage());
            if ($msg !== '') {
                $parts[] = $msg;
            }
            $current = $current->getPrevious();
        }

        return $parts === [] ? 'unknown' : implode("\n", $parts);
    }
}

<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Exceptions;

use Exception;
use Throwable;

class ChainExhaustedException extends Exception
{
    /**
     * @param  list<array{provider: string, model: string, exception: Throwable}>  $attempts
     */
    public function __construct(protected array $attempts, string $message = 'AI failover chain exhausted.')
    {
        parent::__construct($message);
    }

    /**
     * @return list<array{provider: string, model: string, exception: Throwable}>
     */
    public function getAttempts(): array
    {
        return $this->attempts;
    }
}

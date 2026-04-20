<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Tests\Feature;

use Exception;
use IllumaLaw\LlmRouter\Exceptions\ChainExhaustedException;
use IllumaLaw\LlmRouter\FailoverRunner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    config(['llm-router.logging.channel' => 'test']);
});

it('returns the result on the first attempt if it succeeds', function () {
    $runner = app(FailoverRunner::class);
    $chain = [
        ['provider' => 'p1', 'model' => 'm1'],
        ['provider' => 'p2', 'model' => 'm2'],
    ];

    $result = $runner->run($chain, function (string $provider, string $model) {
        return "success from {$provider}";
    });

    expect($result['result'])->toBe('success from p1');
});

it('fails over to the next provider if a retryable exception occurs', function () {
    /** @var mixed $logger */
    $logger = Mockery::mock(LoggerInterface::class);
    Log::shouldReceive('channel')->with('test')->andReturn($logger);
    $logger->shouldReceive('warning')->once();
    $logger->shouldReceive('info')->once();

    $runner = app(FailoverRunner::class);
    $chain = [
        ['provider' => 'p1', 'model' => 'm1'],
        ['provider' => 'p2', 'model' => 'm2'],
    ];

    $attempts = 0;
    $result = $runner->run($chain, function (string $provider, string $model) use (&$attempts) {
        $attempts++;
        if ($attempts === 1) {
            throw new Exception('status code 429');
        }

        return "success from {$provider}";
    });

    expect($result['result'])->toBe('success from p2')
        ->and($attempts)->toBe(2);
});

it('retries the same provider if it is a transient error', function () {
    /** @var mixed $logger */
    $logger = Mockery::mock(LoggerInterface::class);
    Log::shouldReceive('channel')->with('test')->andReturn($logger);
    $logger->shouldReceive('warning')->once();
    $logger->shouldReceive('info')->once();

    config(['llm-router.max_same_provider_retries' => 1]);

    $runner = app(FailoverRunner::class);
    $chain = [
        ['provider' => 'p1', 'model' => 'm1'],
    ];

    $attempts = 0;
    $result = $runner->run($chain, function (string $provider, string $model) use (&$attempts) {
        $attempts++;
        if ($attempts === 1) {
            throw new Exception('request timed out');
        }

        return "success from {$provider}";
    });

    expect($result['result'])->toBe('success from p1')
        ->and($attempts)->toBe(2);
});

it('throws ChainExhaustedException if all attempts fail', function () {
    /** @var mixed $logger */
    $logger = Mockery::mock(LoggerInterface::class);
    Log::shouldReceive('channel')->with('test')->andReturn($logger);
    $logger->shouldReceive('warning')->twice();
    $logger->shouldReceive('error')->once();

    $runner = app(FailoverRunner::class);
    $chain = [
        ['provider' => 'p1', 'model' => 'm1'],
        ['provider' => 'p2', 'model' => 'm2'],
    ];

    $attempts = 0;
    try {
        $runner->run($chain, function (string $provider, string $model) use (&$attempts) {
            $attempts++;
            throw new Exception('status code 429');
        });
    } catch (ChainExhaustedException $e) {
        expect($e->getAttempts())->toHaveCount(2)
            ->and($e->getAttempts()[0]['provider'])->toBe('p1')
            ->and($e->getAttempts()[1]['provider'])->toBe('p2');

        return;
    }

    $this->fail('ChainExhaustedException was not thrown');
});

it('does not retry if the exception is not retryable', function () {
    /** @var mixed $logger */
    $logger = Mockery::mock(LoggerInterface::class);
    Log::shouldReceive('channel')->with('test')->andReturn($logger);
    $logger->shouldReceive('error')->once();

    $runner = app(FailoverRunner::class);
    $chain = [
        ['provider' => 'p1', 'model' => 'm1'],
        ['provider' => 'p2', 'model' => 'm2'],
    ];

    $attempts = 0;
    try {
        $runner->run($chain, function (string $provider, string $model) use (&$attempts) {
            $attempts++;
            throw new Exception('Non-retryable');
        });
    } catch (Exception) {
        //
    }

    expect($attempts)->toBe(1);
});

it('does not retry if failover is disabled', function () {
    config(['llm-router.enabled' => false]);
    /** @var mixed $logger */
    $logger = Mockery::mock(LoggerInterface::class);
    Log::shouldReceive('channel')->with('test')->andReturn($logger);
    $logger->shouldReceive('error')->once();

    $runner = app(FailoverRunner::class);
    $chain = [
        ['provider' => 'p1', 'model' => 'm1'],
        ['provider' => 'p2', 'model' => 'm2'],
    ];

    $attempts = 0;
    try {
        $runner->run($chain, function (string $provider, string $model) use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Fail');
        });
    } catch (ConnectionException $e) {
        expect($e->getMessage())->toBe('Fail');
    }

    expect($attempts)->toBe(1);
});

<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter\Tests\Feature;

use Exception;
use IllumaLaw\LlmRouter\FailoverRunner;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    config(['llm-router.logging.channel' => 'test']);
});

it('stops immediately if exception is terminal', function () {
    /** @var mixed $logger */
    $logger = Mockery::mock(LoggerInterface::class);
    Log::shouldReceive('channel')->with('test')->andReturn($logger);
    $logger->shouldReceive('error')->once();

    $runner = app(FailoverRunner::class);
    $chain = [['provider' => 'p1', 'model' => 'm1'], ['provider' => 'p2', 'model' => 'm2']];

    $attempts = 0;
    try {
        $runner->run($chain, function () use (&$attempts) {
            $attempts++;
            throw new Exception('Terminal failure');
        });
    } catch (Exception) {
        //
    }

    expect($attempts)->toBe(1);
});

it('logs success with correct duration', function () {
    /** @var mixed $logger */
    $logger = Mockery::mock(LoggerInterface::class);
    Log::shouldReceive('channel')->with('test')->andReturn($logger);
    $logger->shouldReceive('info')->once()->with('ai.fallback_success', Mockery::on(function (mixed $payload) {
        return is_array($payload) && isset($payload['duration_ms']) && is_float($payload['duration_ms']);
    }));

    $runner = app(FailoverRunner::class);
    $runner->run([['provider' => 'p', 'model' => 'm']], fn () => 'ok');
});

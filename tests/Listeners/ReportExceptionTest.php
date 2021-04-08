<?php

namespace Laravel\Octane\Listeners;

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Exceptions\DdException;
use Laravel\Octane\Stream;
use Laravel\Octane\Tests\TestCase;
use Mockery;

class ReportExceptionTest extends TestCase
{
    /** @doesNotPerformAssertions @test */
    public function test_exceptions_are_streamed()
    {
        [$app, $worker] = $this->createOctaneContext([]);

        $exception = new Exception('foo');

        Mockery::mock('alias:'.Stream::class)
            ->shouldReceive('throwable')
            ->once()
            ->with($exception);

        $worker->dispatchEvent($app, new WorkerErrorOccurred($exception, $app));
    }

    /** @doesNotPerformAssertions @test */
    public function test_exceptions_are_reported()
    {
        [$app, $worker] = $this->createOctaneContext([]);

        $exception = new Exception('foo');

        $exceptionHandler = tap(Mockery::mock(ExceptionHandler::class), fn ($mock) => $mock
            ->shouldReceive('report')
            ->once()
            ->with($exception));

        Mockery::mock('alias:'.Stream::class)
            ->shouldReceive('throwable')
            ->once()
            ->with($exception);

        $app->bind(ExceptionHandler::class, fn () => $exceptionHandler);

        $worker->dispatchEvent($app, new WorkerErrorOccurred($exception, $app));
    }

    /** @doesNotPerformAssertions @test */
    public function test_dd_calls_are_not_streamed()
    {
        [$app, $worker] = $this->createOctaneContext([]);

        $exception = new DdException(['foo' => 'bar']);

        Mockery::mock('alias:'.Stream::class)
            ->shouldReceive('throwable')
            ->never();

        $worker->dispatchEvent($app, new WorkerErrorOccurred($exception, $app));
    }
}

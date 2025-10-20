<?php

namespace Laravel\Octane\Swoole;

use Laravel\Octane\Contracts\DispatchesCoroutines;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

class SwooleCoroutineDispatcher implements DispatchesCoroutines
{
    public function __construct(protected bool $withinCoroutineContext)
    {
    }

    /**
     * Concurrently resolve the given callbacks via coroutines, returning the results.
     */
    public function resolve(array $coroutines, int $waitMilliseconds = -1): array
    {
        $results = [];

        $callback = function () use (&$results, $coroutines, $waitMilliseconds) {
            $waitGroup = new WaitGroup;

            foreach ($coroutines as $key => $callback) {
                Coroutine::create(function () use ($key, $callback, $waitGroup, &$results) {
                    $waitGroup->add();

                    $results[$key] = $callback();

                    $waitGroup->done();
                });
            }

            // Convert milliseconds to seconds for Swoole's wait method
            $waitSeconds = $waitMilliseconds === -1 ? -1 : $waitMilliseconds / 1000;
            $waitGroup->wait($waitSeconds);
        };

        if (! $this->withinCoroutineContext) {
            Coroutine\run($callback);
        } else {
            $callback();
        }

        return $results;
    }
}

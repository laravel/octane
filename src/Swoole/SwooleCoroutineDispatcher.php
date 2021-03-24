<?php

namespace Laravel\Octane\Swoole;

use Laravel\Octane\Contracts\DispatchesCoroutines;
use Swoole\Coroutine\WaitGroup;

class SwooleCoroutineDispatcher implements DispatchesCoroutines
{
    /**
     * Concurrently resolve the given callbacks via coroutines, returning the results.
     *
     * @param  array  $coroutines
     * @param  int  $waitSeconds
     * @return array
     */
    public function resolve(array $coroutines, int $waitSeconds = -1)
    {
        $results = [];

        $waitGroup = new WaitGroup;

        foreach ($coroutines as $key => $callback) {
            go(function () use ($key, $callback, $waitGroup, &$results) {
                $waitGroup->add();

                $results[$key] = $callback();

                $waitGroup->done();
            });
        }

        $waitGroup->wait($waitSeconds);

        return $results;
    }
}

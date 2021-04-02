<?php

namespace Laravel\Octane;

use Laravel\Octane\Contracts\DispatchesCoroutines;

class SequentialCoroutineDispatcher implements DispatchesCoroutines
{
    /**
     * Concurrently resolve the given callbacks via coroutines, returning the results.
     *
     * @param  array  $coroutines
     * @param  int  $waitSeconds
     * @return array
     */
    public function resolve(array $coroutines, int $waitSeconds = -1): array
    {
        return collect($coroutines)->mapWithKeys(
            fn ($coroutine, $key) => [$key => $coroutine()]
        )->all();
    }
}

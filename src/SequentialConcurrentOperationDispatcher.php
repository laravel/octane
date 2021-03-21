<?php

namespace Laravel\Octane;

use Laravel\Octane\Contracts\DispatchesTasks;

class SequentialTaskDispatcher implements DispatchesTasks
{
    /**
     * Concurrently resolve the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     * @param  array  $tasks
     * @param  int  $waitMilliseconds
     * @return array
     */
    public function resolve(array $tasks, int $waitMilliseconds = 1): array
    {
        return collect($tasks)->mapWithKeys(
            fn ($task, $key) => [$key => $task()]
        )->all();
    }

    /**
     * Concurrently dispatch the given callbacks via background tasks.
     *
     * @param  array  $tasks
     * @return void
     */
    public function dispatch(array $tasks)
    {
        return $this->resolve($tasks);
    }
}

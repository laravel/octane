<?php

namespace Laravel\Octane;

use Laravel\Octane\Contracts\ConcurrentOperationDispatcher;

class SequentialConcurrentOperationDispatcher implements ConcurrentOperationDispatcher
{
    /**
     * Concurrently resolve the given callbacks, returning the results.
     *
     * @param  array  $callbacks
     * @param  int  $waitSeconds
     * @return array
     */
    public function resolve(array $callbacks, int $waitSeconds = -1): array
    {
        $results = [];

        foreach ($callbacks as $key => $callback) {
            $results[$key] = $callback();
        }

        return $results;
    }

    /**
     * Concurrently resolve the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     * @param  array  $tasks
     * @param  int  $waitMilliseconds
     * @return array
     */
    public function resolveTasks(array $tasks, int $waitMilliseconds = 1): array
    {
        return $this->resolve($tasks);
    }

    /**
     * Concurrently dispatch the given callbacks via background tasks.
     *
     * @param  array  $tasks
     * @return void
     */
    public function dispatchTasks(array $tasks)
    {
        return $this->resolve($tasks);
    }
}

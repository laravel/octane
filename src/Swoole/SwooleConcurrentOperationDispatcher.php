<?php

namespace Laravel\Octane\Swoole;

use Closure;
use Illuminate\Queue\SerializableClosure;
use InvalidArgumentException;
use Swoole\Coroutine\WaitGroup;
use Swoole\Http\Server;

class SwooleConcurrentOperationDispatcher
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

        \Co\run(function () use (&$results, $callbacks, $waitSeconds) {
            $waitGroup = new WaitGroup;

            foreach ($callbacks as $key => $callback) {
                go(function () use ($key, $callback, $waitGroup, &$results) {
                    $waitGroup->add();

                    $results[$key] = $callback();

                    $waitGroup->done();
                });
            }

            $waitGroup->wait($waitSeconds);
        });

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
    public function resolveTasks(array $tasks, int $waitMilliseconds = 3000): array
    {
        if (! app()->bound(Server::class)) {
            throw new InvalidArgumentException("Tasks can only be resolved within a Swoole server context / web request.");
        }

        $results = app(Server::class)->taskWaitMulti(collect($tasks)->mapWithKeys(function ($task, $key) {
            return [$key => $task instanceof Closure
                            ? new SerializableClosure($task)
                            : $task, ];
        })->all(), $waitMilliseconds);

        if ($results === false) {
            return collect($tasks)->mapWithKeys(fn ($value, $key) => [$key => false])->all();
        }

        $i = 0;

        foreach ($tasks as $key => $task) {
            $tasks[$key] = $results[$i] ?? false;

            $i++;
        }

        return $tasks;
    }

    /**
     * Concurrently dispatch the given callbacks via background tasks.
     *
     * @param  array  $tasks
     * @return void
     */
    public function dispatchTasks(array $tasks)
    {
        if (! app()->bound(Server::class)) {
            throw new InvalidArgumentException("Tasks can only be dispatched within a Swoole server context / web request.");
        }

        $server = app(Server::class);

        collect($tasks)->each(function ($task) use ($server) {
            $server->task($task instanceof Closure ? new SerializableClosure($task) : $task);
        });
    }
}

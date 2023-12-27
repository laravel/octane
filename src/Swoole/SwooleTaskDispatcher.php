<?php

namespace Laravel\Octane\Swoole;

use Closure;
use InvalidArgumentException;
use Laravel\Octane\Contracts\DispatchesTasks;
use Laravel\Octane\Exceptions\TaskExceptionResult;
use Laravel\Octane\Exceptions\TaskTimeoutException;
use Laravel\SerializableClosure\SerializableClosure;

class SwooleTaskDispatcher implements DispatchesTasks
{
    protected string $serverClass;

    public function __construct()
    {
        $this->serverClass = config('octane.swoole.enableWebSocket', false)
            ? \Swoole\Websocket\Server::class
            : \Swoole\Http\Server::class;
    }

    /**
     * Concurrently resolve the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     *
     * @throws \Laravel\Octane\Exceptions\TaskException
     * @throws \Laravel\Octane\Exceptions\TaskTimeoutException
     */
    public function resolve(array $tasks, int $waitMilliseconds = 3000): array
    {
        if (! app()->bound($this->serverClass)) {
            throw new InvalidArgumentException('Tasks can only be resolved within a Swoole server context / web request.');
        }

        $results = app($this->serverClass)->taskWaitMulti(collect($tasks)->mapWithKeys(function ($task, $key) {
            return [$key => $task instanceof Closure
                ? new SerializableClosure($task)
                : $task, ];
        })->all(), $waitMilliseconds / 1000);

        if ($results === false) {
            throw TaskTimeoutException::after($waitMilliseconds);
        }

        $i = 0;

        foreach ($tasks as $key => $task) {
            if (isset($results[$i])) {
                if ($results[$i] instanceof TaskExceptionResult) {
                    throw $results[$i]->getOriginal();
                }

                $tasks[$key] = $results[$i]->result;
            } else {
                $tasks[$key] = false;
            }

            $i++;
        }

        return $tasks;
    }

    /**
     * Concurrently dispatch the given callbacks via background tasks.
     */
    public function dispatch(array $tasks): void
    {
        if (! app()->bound($this->serverClass)) {
            throw new InvalidArgumentException('Tasks can only be dispatched within a Swoole server context / web request.');
        }

        $server = app($this->serverClass);

        collect($tasks)
            ->each(fn ($task) => $server->task($task instanceof Closure ? new SerializableClosure($task) : $task));
    }
}

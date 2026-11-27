<?php

namespace Laravel\Octane\Swoole;

use Closure;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Contracts\DispatchesTasks;
use Laravel\Octane\Exceptions\TaskExceptionResult;
use Laravel\Octane\Exceptions\TaskTimeoutException;
use Laravel\SerializableClosure\SerializableClosure;

class SwooleHttpTaskDispatcher implements DispatchesTasks
{
    public function __construct(
        protected string $host,
        protected string $port,
        protected DispatchesTasks $fallbackDispatcher,
        protected ?string $secret = null
    ) {
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
        $tasks = collect($tasks)->mapWithKeys(function ($task, $key) {
            return [$key => $task instanceof Closure
                            ? new SerializableClosure($task)
                            : $task, ];
        })->all();

        if ($this->secret === null) {
            return $this->fallbackDispatcher->resolve($tasks, $waitMilliseconds);
        }

        try {
            $response = Http::timeout(($waitMilliseconds / 1000) + 5)->post("http://{$this->host}:{$this->port}/octane/resolve-tasks", [
                'tasks' => TaskPayload::seal($this->secret, 'resolve-tasks', ['tasks' => $tasks, 'wait' => $waitMilliseconds]),
            ]);
        } catch (ConnectionException) {
            return $this->fallbackDispatcher->resolve($tasks, $waitMilliseconds);
        }

        if ($response->status() === 504) {
            throw TaskTimeoutException::after($waitMilliseconds);
        }

        try {
            if ($response->status() === 200) {
                return TaskPayload::open($this->secret, 'resolve-tasks-result', $response->body());
            }
        } catch (DecryptException) {
            //
        }

        throw TaskExceptionResult::from(
            new Exception('Invalid response from task server.'),
        )->getOriginal();
    }

    /**
     * Concurrently dispatch the given callbacks via background tasks.
     */
    public function dispatch(array $tasks): void
    {
        $tasks = collect($tasks)->mapWithKeys(function ($task, $key) {
            return [$key => $task instanceof Closure
                            ? new SerializableClosure($task)
                            : $task, ];
        })->all();

        if ($this->secret === null) {
            $this->fallbackDispatcher->dispatch($tasks);

            return;
        }

        try {
            $response = Http::post("http://{$this->host}:{$this->port}/octane/dispatch-tasks", [
                'tasks' => TaskPayload::seal($this->secret, 'dispatch-tasks', $tasks),
            ]);

            if ($response->status() === 403) {
                $this->fallbackDispatcher->dispatch($tasks);
            }
        } catch (ConnectionException) {
            $this->fallbackDispatcher->dispatch($tasks);
        }
    }
}

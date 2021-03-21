<?php

namespace Laravel\Octane\Swoole;

use Closure;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\SerializableClosure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Contracts\DispatchesTasks;

class SwooleHttpTaskDispatcher implements DispatchesTasks
{
    /**
     * The dispatcher to use if communication with the server can't be established.
     *
     * @var \Laravel\Octane\Contracts\DispatchesTasks
     */
    protected $fallbackDispatcher;

    /**
     * The host of the currently running Swoole server.
     *
     * @var string
     */
    protected $host;

    /**
     * The port of the currently running Swoole server.
     *
     * @var string
     */
    protected $port;

    /**
     *
     */
    public function __construct(ServerStateFile $serverStateFile,
                                DispatchesTasks $fallbackDispatcher)
    {
        $this->fallbackDispatcher = $fallbackDispatcher;

        $serverState = $serverStateFile->read();

        $this->host = $serverState['state']['host'] ?? '127.0.0.1';
        $this->port = $serverState['state']['port'] ?? 8000;
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
    public function resolve(array $tasks, int $waitMilliseconds = 3000): array
    {
        $tasks = collect($tasks)->mapWithKeys(function ($task, $key) {
            return [$key => $task instanceof Closure
                            ? new SerializableClosure($task)
                            : $task, ];
        })->all();

        try {
            $response = Http::post("http://{$this->host}:{$this->port}/octane/resolve-tasks", [
                'tasks' => Crypt::encryptString(serialize($tasks)),
                'wait' => $waitMilliseconds,
            ]);

            return $response->status() === 200
                        ? unserialize($response)
                        : throw new Exception("Invalid response from task server.");
        } catch (ConnectionException $e) {
            return $this->fallbackDispatcher->resolve($tasks, $waitMilliseconds);
        }
    }

    /**
     * Concurrently dispatch the given callbacks via background tasks.
     *
     * @param  array  $tasks
     * @return void
     */
    public function dispatch(array $tasks)
    {
        $tasks = collect($tasks)->mapWithKeys(function ($task, $key) {
            return [$key => $task instanceof Closure
                            ? new SerializableClosure($task)
                            : $task, ];
        })->all();

        try {
            Http::post("http://{$this->host}:{$this->port}/octane/dispatch-tasks", [
                'tasks' => Crypt::encryptString(serialize($tasks)),
            ]);
        } catch (ConnectionException $e) {
            return $this->fallbackDispatcher->dispatch($tasks);
        }
    }
}

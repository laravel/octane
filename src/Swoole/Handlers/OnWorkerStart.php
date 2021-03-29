<?php

namespace Laravel\Octane\Swoole\Handlers;

use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Stream;
use Laravel\Octane\Swoole\SwooleClient;
use Laravel\Octane\Swoole\WorkerState;
use Laravel\Octane\Worker;
use Swoole\Http\Server;
use Throwable;

class OnWorkerStart
{
    public function __construct(protected $basePath,
                                protected array $serverState,
                                protected WorkerState $workerState)
    {
    }

    /**
     * Handle the "workerstart" Swoole event.
     *
     * @param  \Swoole\Http\Server  $server
     * @param  int  $workerId
     * @return void
     */
    public function __invoke($server, int $workerId)
    {
        $this->workerState->workerId = $workerId;

        $this->workerState->worker = $this->bootWorker($server);

        $this->dispatchServerTickTaskEverySecond($server);
        $this->streamRequestsToConsole($server);
    }

    /**
     * Boot the Octane worker and application.
     *
     * @param  \Swoole\Http\Server  $server
     * @return \Laravel\Octane\Worker
     */
    protected function bootWorker($server)
    {
        try {
            return tap(new Worker(
                new ApplicationFactory($this->basePath),
                $this->workerState->client = new SwooleClient
            ))->boot([
                'octane.cacheTable' => $this->workerState->cacheTable,
                Server::class => $server,
                WorkerState::class => $this->workerState,
            ]);
        } catch (Throwable $e) {
            Stream::error($e);

            $server->shutdown();
        }
    }

    /**
     * Start the Octane server tick to dispatch the tick task every second.
     *
     * @param  \Swoole\Http\Server  $server
     * @return void
     */
    protected function dispatchServerTickTaskEverySecond($server)
    {
        // if ($this->workerState->workerId === 0 &&
        //     ($this->serverState['octaneConfig']['tick'] ?? true)) {
        //     $this->workerState->tickTimerId = $server->tick(1000, function () use ($server) {
        //         $server->task('octane-tick');
        //     });
        // }
    }

    /**
     * Register the request handled listener that will output request information per request.
     *
     * @param  \Swoole\Http\Server  $server
     * @return void
     */
    protected function streamRequestsToConsole($server)
    {
        $this->workerState->worker->onRequestHandled(function ($request, $response, $sandbox) {
            if (! $sandbox->environment('local')) {
                return;
            }

            Stream::request(
                $request->getMethod(),
                $request->fullUrl(),
                $response->getStatusCode(),
                (microtime(true) - $this->workerState->lastRequestTime) * 1000,
            );
        });
    }
}

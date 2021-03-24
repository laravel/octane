<?php

namespace Laravel\Octane\Swoole\Handlers;

use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Swoole\PerRequestConsoleOutput;
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

        $this->registerServerTick($server);
        $this->registerRequestOutputHandler($server);
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
                Server::class => $server,
                'octane.cacheTable' => $this->workerState->cacheTable,
            ]);
        } catch (Throwable $e) {
            fwrite(STDERR, (string) $e);

            $server->shutdown();
        }
    }

    /**
     * Start the Octane server tick.
     *
     * @param  \Swoole\Http\Server  $server
     * @return void
     */
    protected function registerServerTick($server)
    {
        if ($this->workerState->workerId === 0 &&
            ($this->serverState['octaneConfig']['tick'] ?? true)) {
            $this->workerState->tickTimerId = $server->tick(1000, function () use ($server) {
                $server->task('octane-tick');
            });
        }
    }

    /**
     * Register the request handled listener that will output request information per request.
     *
     * @param  \Swoole\Http\Server  $server
     * @return void
     */
    protected function registerRequestOutputHandler($server)
    {
        $this->workerState->worker->onRequestHandled(function ($request, $response, $sandbox) {
            return $sandbox->environment('local')
                        ? PerRequestConsoleOutput::write(
                            STDERR,
                            $request,
                            $response,
                            $this->workerState->lastRequestTime,
                            $sandbox
                        )
                        : null;
        });
    }
}

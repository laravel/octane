<?php

namespace Laravel\Octane\Swoole\Handlers;

use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Swoole\PerRequestConsoleOutput;
use Laravel\Octane\Swoole\SwooleClient;
use Laravel\Octane\Worker;
use Laravel\Octane\Stream;
use Swoole\Http\Server;

class OnWorkerStart
{
    /**
     * Handle the "workerstart" Swoole event.
     *
     * @param  \Swoole\Http\Server  $server
     * @param  callable  $bootstrap
     * @param  stdClass  $workerState
     * @return void
     */
    public function __invoke($server, $basePath, $workerState)
    {
        $workerState->worker = tap(new Worker(
            new ApplicationFactory($basePath),
            $workerState->client = new SwooleClient
        ))->boot([
            Server::class => $server,
            'octane.cacheTable' => $workerState->cacheTable,
        ]);

        $workerState->worker->onRequestHandled(function ($request, $response, $sandbox) use ($workerState) {
            if ($sandbox->environment('local')) {
                Stream::request(
                    $request->getMethod(),
                    $request->fullUrl(),
                    $response->getStatusCode(),
                    (microtime(true) - $workerState->lastRequestTime) * 1000,
                );
            }
        });
    }
}

<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Pam\PamClient;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Stream;
use Laravel\Octane\Worker;
use Pam\Http\Server as PamServer;
use Symfony\Component\Console\Output\ConsoleOutput;

if (! class_exists(PamServer::class)) {
    echo 'PAM must be used to run this script.';

    exit(1);
}

$basePath = require __DIR__.'/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Start The Octane Worker
|--------------------------------------------------------------------------
|
| Next we will start the Octane worker, which is a long running process to
| handle incoming requests to the application. This worker will be used
| by PAM to serve an entire Laravel application at high speed.
|
*/

$pamClient = new PamClient;

try {
    $worker = tap(new Worker(
        new ApplicationFactory($basePath), $pamClient
    ))->boot();
} catch (Throwable $e) {
    $container = Container::getInstance();

    $container->bound(ExceptionHandler::class)
        ? $container->make(ExceptionHandler::class)->renderForConsole(new ConsoleOutput, $e)
        : fwrite(STDERR, (string) $e.PHP_EOL);

    exit(1);
}

$app = $worker->application();

$octaneConfig = $app['config']->get('octane');

$debugMode = $app['config']->get('app.debug');

$requestStartTime = 0;

$worker->onRequestHandled(function ($request, $response, $sandbox) use (&$requestStartTime) {
    if (! $sandbox->environment('local', 'testing')) {
        return;
    }

    Stream::request(
        $request->getMethod(),
        $request->fullUrl(),
        $response->getStatusCode(),
        (microtime(true) - $requestStartTime) * 1000,
    );
});

$maxExecutionTime = (int) ($_SERVER['REQUEST_MAX_EXECUTION_TIME'] ?? $octaneConfig['max_execution_time'] ?? 30);

/*
|--------------------------------------------------------------------------
| Handle Incoming Requests
|--------------------------------------------------------------------------
|
| PAM owns the sockets, HTTP parsing, worker supervision and request time
| limits. For every request it fills the PHP request globals and invokes
| this handler, which hands the request to the warm Octane worker.
|
*/

PamServer::createNative(function ($pamResponse) use ($worker, $pamClient, $app, $octaneConfig, $debugMode, &$requestStartTime) {
    $requestStartTime = microtime(true);

    try {
        [$request, $context] = $pamClient->marshalRequest(new RequestContext([
            'pamResponse' => $pamResponse,
            'publicPath' => $app->publicPath(),
            'octaneConfig' => $octaneConfig,
        ]));

        $worker->handle($request, $context);
    } catch (Throwable $e) {
        report($e);

        @http_response_code(500);
        header('Content-Type: text/plain');

        echo $debugMode ? (string) $e : 'Internal Server Error';

        Stream::shutdown($e);
    }

    return $pamResponse;
})->listen((int) $_SERVER['OCTANE_PORT'], $_SERVER['OCTANE_HOST'], array_filter([
    'maxConcurrentRequests' => 1,
    'maxResponseBytes' => 256 * 1024 * 1024,
    'requestTimeoutMs' => $maxExecutionTime > 0 ? $maxExecutionTime * 1000 : 365 * 24 * 3600 * 1000,
    'tlsCert' => $_SERVER['PAM_TLS_CERT'] ?? null,
    'tlsKey' => $_SERVER['PAM_TLS_KEY'] ?? null,
]));

// PAM starts serving once this script returns, and shuts the engine down when the worker stops...
register_shutdown_function(fn () => $worker->terminate());

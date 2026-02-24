<?php

use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\FrankenPhp\FrankenPhpClient;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Stream;
use Laravel\Octane\Worker;
use Symfony\Component\HttpFoundation\Response;

if ((! ($_SERVER['FRANKENPHP_WORKER'] ?? false)) || ! function_exists('frankenphp_handle_request')) {
    echo 'FrankenPHP must be in worker mode to use this script.';

    exit(1);
}

ignore_user_abort(true);

$basePath = require __DIR__.'/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Start The Octane Worker
|--------------------------------------------------------------------------
|
| Next we will start the Octane worker, which is a long running process to
| handle incoming requests to the application. This worker will be used
| by FrankenPHP to serve an entire Laravel application at high speed.
|
*/

$frankenPhpClient = new FrankenPhpClient();

$worker = null;
$requestCount = 0;
$maxRequests = $_ENV['MAX_REQUESTS'] ?? $_SERVER['MAX_REQUESTS'] ?? 1000;
$requestMaxExecutionTime = $_ENV['REQUEST_MAX_EXECUTION_TIME'] ?? $_SERVER['REQUEST_MAX_EXECUTION_TIME'] ?? null;

if (PHP_OS_FAMILY === 'Linux' && ! is_null($requestMaxExecutionTime)) {
    set_time_limit((int) $requestMaxExecutionTime);
}

try {
    $handleRequest = static function () use (&$worker, $basePath, $frankenPhpClient) {
        try {
            $worker ??= tap(
                new Worker(
                    new ApplicationFactory($basePath), $frankenPhpClient
                )
            )->boot();

            [$request, $context] = $frankenPhpClient->marshalRequest(new RequestContext());

            $worker->handle($request, $context);
        } catch (Throwable $e) {
            if ($worker) {
                report($e);
            }

            $response = new Response(
                'Internal Server Error',
                500,
                [
                    'Status' => '500 Internal Server Error',
                    'Content-Type' => 'text/plain',
                ],
            );

            $response->send();

            Stream::shutdown($e);
        }
    };

    while ($requestCount < $maxRequests && frankenphp_handle_request($handleRequest)) {
        $requestCount++;
    }
} finally {
    $worker?->terminate();

    gc_collect_cycles();
}

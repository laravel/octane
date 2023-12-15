<?php

use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\FrankenPhp\FrankenPhpClient;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Stream;
use Laravel\Octane\Worker;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
$maxRequests = $_ENV['MAX_REQUESTS'] ?? $_SERVER['MAX_REQUESTS'];

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
            $response = new Response('', 500, []);

            $response->send();

            if ($worker) {
                report($e);
            }

            Stream::shutdown($e);
        }
    };

    while ($requestCount < $maxRequests && frankenphp_handle_request($handleRequest)) {
        $requestCount++;
    }
} finally {
    $worker?->terminate();
}

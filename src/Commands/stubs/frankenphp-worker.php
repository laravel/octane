<?php

use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\FrankenPhp\FrankenPhpClient;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Worker;


if ((! ($_SERVER['FRANKENPHP_WORKER'] ?? false)) || ! function_exists('frankenphp_handle_request')) {
    echo 'You need FrankenPHP in worker mode to use this script.';

    exit(1);
}

$basePath = require __DIR__.'/../vendor/laravel/octane/bin/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Start The Octane Worker
|--------------------------------------------------------------------------
|
| Next we will start the Octane worker, which is a long running process to
| handle incoming requests to the application.
|
*/

$frankenPhpClient = new FrankenPhpClient();

$worker = null;
$nbRequests = 0;
try {
    $handleRequest = static function () use (&$worker, $basePath, $frankenPhpClient) {
        $worker ??= tap(
            new Worker(
                new ApplicationFactory($basePath), $frankenPhpClient
            )
        )->boot();

        [$request, $context] = $frankenPhpClient->marshalRequest(new RequestContext());

        $worker->handle($request, $context);
    };
    while (
        $nbRequests < ($_ENV['MAX_REQUESTS'] ?? $_SERVER['MAX_REQUESTS']) &&
        frankenphp_handle_request($handleRequest)
    ) {
        $nbRequests++;
    }
} finally {
    $worker?->terminate();
}

<?php

use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\FrankenPhp\FrankenPhpClient;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Worker;

$basePath = require __DIR__.'/bootstrap.php';

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
    while (
        $nbRequests < ($_ENV['MAX_REQUESTS'] ?? $_SERVER['MAX_REQUESTS']) &&
        frankenphp_handle_request(function () use (&$worker, $basePath, $frankenPhpClient) {
            $worker = $worker ?: tap(
                new Worker(
                    new ApplicationFactory($basePath), $frankenPhpClient
                )
            )->boot();

            [$request, $context] = $frankenPhpClient->marshalRequest(new RequestContext());

            $worker->handle($request, $context);
        })
    ) {
        $nbRequests++;
    }
} finally {
    $worker?->terminate();
}

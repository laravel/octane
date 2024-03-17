<?php

use Laravel\Octane\Exceptions\DdException;
use Laravel\Octane\Octane;

ini_set('display_errors', 'stderr');

$_ENV['APP_RUNNING_IN_CONSOLE'] = false;
$_SERVER['VAR_DUMPER_FORMAT'] = 'html';

/*
|--------------------------------------------------------------------------
| Find Application Base Path
|--------------------------------------------------------------------------
|
| First we need to locate the path to the application bootstrapper, which
| is able to create a fresh copy of the Laravel application for us and
| we can use this to handle requests. For now we just need the path.
|
*/

$basePath = $_SERVER['APP_BASE_PATH'] ?? $_ENV['APP_BASE_PATH'] ?? $serverState['octaneConfig']['base_path'] ?? null;

if (! is_string($basePath)) {
    Octane::writeError('Cannot find application base path.');

    exit(11);
}

if (! function_exists('dd')) {
    function dd(...$vars)
    {
        throw new DdException($vars);
    }
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script here so that we do not have to worry about the
| loading of any our classes "manually". Feels great to relax.
|
*/

if (! is_file($autoload_file = $basePath.'/vendor/autoload.php')) {
    Octane::writeError("Composer autoload file was not found. Did you install the project's dependencies?");

    exit(10);
}

require_once $autoload_file;

return $basePath;

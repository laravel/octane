<?php

ini_set('display_errors', 'stderr');

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

$loaded = false;

// TODO: Remove octane-app test directory...
foreach (array_filter([
    $serverState['octaneConfig']['vendor_path'] ?? null,
    '../../..',
    '../..',
    '../../octane-app/vendor',
    '..',
    'vendor',
    '../vendor',
    '../../vendor'
]) as $path) {
    if (is_file($autoload_file = __DIR__ . '/' . $path . '/autoload.php')) {
        require $autoload_file;

        $loaded = true;

        break;
    }
}

if ($loaded !== true) {
    fwrite(STDERR, "Composer autoload file was not found. Did you install the project's dependencies?".PHP_EOL);

    exit(10);
}

/*
|--------------------------------------------------------------------------
| Find Application Base Path
|--------------------------------------------------------------------------
|
| Next, we need to locate the path to the application bootstrapper, which
| is able to create a fresh copy of the Laravel application for us and
| we can use this to handle requests. For now we just need the path.
|
*/

$basePath = null;

// TODO: Remove octane-app test directory...
foreach (array_filter([
    $serverState['octaneConfig']['base_path'] ?? null,
    '../../../..',
    '../../..',
    '../..',
    '..',
    '../../octane-app',
    '../vendor/laravel/laravel'
]) as $path) {
    if (is_file(__DIR__.'/'.$path.'/bootstrap/app.php')) {
        $basePath = realpath(__DIR__.'/'.$path);

        break;
    }
}

if (! is_string($basePath)) {
    fwrite(STDERR, 'Cannot find application base path.' . PHP_EOL);

    exit(11);
}

return $basePath;

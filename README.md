<p align="center"><img src="https://laravel.com/assets/img/components/logo-octane.svg"></p>

<p align="center">
<a href="https://github.com/laravel/octane/actions"><img src="https://github.com/laravel/octane/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/octane"><img src="https://img.shields.io/packagist/dt/laravel/octane" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/octane"><img src="https://img.shields.io/packagist/v/laravel/octane" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/octane"><img src="https://img.shields.io/packagist/l/laravel/octane" alt="License"></a>
</p>

## Introduction

Laravel Octane supercharges your application's performance by serving your application using high-powered application servers, including [Swoole](https://swoole.co.uk) and [RoadRunner](https://roadrunner.dev). Octane boots your application once, keeps it in memory, and then feeds it requests at supersonic speeds.

## Documentation

**IMPORTANT: Laravel Octane is within a beta period. It should only be used for local development and testing in order to improve the quality of the library and resolve any existing bugs.**

### Installation

Octane may be installed via the Composer package manager:

```bash
composer require laravel/octane
```

After installing Octane, you may execute the `octane:install` Artisan command:

```bash
php artisan octane:install
```

### Server Prerequisites

> **Laravel Octane requires [PHP 8.0+](https://php.net/releases/)**

#### RoadRunner

RoadRunner is powered by the RoadRunner binary, which is built using Go. The first time you start a RoadRunner based Octane server, Octane will offer to download and install the RoadRunner binary for you.

##### RoadRunner Via Laravel Sail

If you plan to develop your application using [Laravel Sail](https://laravel.com/docs/sail), you should run the following commands to install Octane and RoadRunner:

```bash
./vendor/bin/sail up

./vendor/bin/sail composer require spiral/roadrunner
```

Next, you should start a Sail shell and use the `rr` executable to retrieve the latest Linux based build of the RoadRunner binary:

```bash
./vendor/bin/sail shell

# Within the Sail shell...
./vendor/bin/rr get-binary
```

After installing the RoadRunner binary, you may exit your Sail shell session. You will now need to adjust the `supervisor.conf` file used by Sail to keep your application running. To get started, execute the `sail:publish` Artisan command:

```bash
./vendor/bin/sail artisan sail:publish
```

Next, update the `command` directive of your application's `docker/supervisord.conf` file so that Sail serves your application using Octane instead of the PHP development server:

```ini
command=/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan octane:start --server=roadrunner --host=0.0.0.0 --port=80
```

Next, ensure the `rr` binary is executable and build your Sail images:

```bash
chmod +x ./rr

./vendor/bin/sail build
```

#### Swoole

If you plan to use the Swoole application server to serve your Laravel Octane application, you must install the Swoole PHP extension. Typically, this can be done via PECL:

```bash
pecl install swoole
```

##### Swoole Via Laravel Sail

> **Note:** Before serving an Octane application via Sail, ensure you have the latest version of Laravel Sail and execute `./vendor/bin/sail build --no-cache` within your application's root directory.

Alternatively, you may develop your Swoole based Octane application using [Laravel Sail](https://laravel.com/docs/sail), the official Docker based development environment for Laravel. Laravel Sail includes the Swoole extension by default. However, you will still need to adjust the `supervisor.conf` file used by Sail to keep your application running. To get started, execute the `sail:publish` Artisan command:

```bash
./vendor/bin/sail artisan sail:publish
```

Next, update the `command` directive of your application's `docker/supervisord.conf` file so that Sail serves your application using Octane instead of the PHP development server:

```ini
command=/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan octane:start --server=swoole --host=0.0.0.0 --port=80
```

Next, build your Sail images:

```bash
./vendor/bin/sail build
```

### Serving Your Application

The Octane server can be started via the `octane:start` Artisan command. By default, this command will utilize the server specified by the `server` configuration option of your application's `octane` configuration file:

```bash
php artisan octane:start
```

By default, Octane will start the server on port 8000, so you may access your application in a web browser via `http://localhost:8000`.

#### Serving Your Application Via HTTPS

By default, applications running via Octane generate links prefixed with `http://`. The `OCTANE_HTTPS` environment variable, used within your application's `config/octane.php` configuration file, can be set to `true` when serving your application via HTTPS. When this configuration value is set to `true`, Octane will instruct Laravel to prefix all generated links with `https://`:

```php
'https' => env('OCTANE_HTTPS', false),
```

#### Watching For File Changes

Since your application is loaded in memory once when the Octane server starts, any changes to your application's files will not be reflected when you refresh your browser. For example, route definitions added to your `routes/web.php` file will not be reflected until the server is restarted. For convenience, you may use the `--watch` flag to instruct Octane to automatically restart the server on any file changes within your application:

```bash
php artisan octane:start --watch
```

Before using this feature, you should ensure that [Node](https://nodejs.org) is installed within your local development environment. In addition, you should install the [Chokidar](https://github.com/paulmillr/chokidar) file-watching library within your project:library:

```bash
npm install --save-dev chokidar
```

You may configure the directories and files that should be watched using the `watch` configuration option within your application's `config/octane.php` configuration file.

#### Specifying The Worker Count

By default, Octane will start an application request worker for each CPU core provided by your machine. However, you may manually specify how many workers you would like to start using the `--workers` option when invoking the `octane:start` command:

```bash
php artisan octane:start --workers=4
```

If you are using the Swoole application server, you may also specify how many "task workers" you wish to start:

```bash
php artisan octane:start --workers=4 --task-workers=6
```

#### Specifying The Max Request Count

To help prevent stray memory leaks, Octane can gracefully restart a worker once it has handled a given number of requests. To instruct Octane to do this, you may use the `--max-requests` option:

```bash
php artisan octane:start --max-requests=250
```

#### Reloading The Application Workers

You may gracefully restart the Octane server's application workers using the `octane:reload` command. Typically, this should be done after deployment:

```bash
php artisan octane:reload
```

#### Checking The Server Status

You may check the current status of the Octane server using the `octane:status` Artisan command:

```bash
php artisan octane:status
```

### Stopping The Server

You may stop the Octane server using the `octane:stop` Artisan command:

```bash
php artisan octane:stop
```

### Dependency Injection & Octane

Since Octane boots your application once and keeps it in memory while serving requests, there are a few caveats you should consider while building your application. For example, the `register` and `boot` methods of your application's service providers will only be executed once when the request worker initially boots. On subsequent requests, the same application instance will be reused.

In light of this, you should take special care when injecting the application service container or request into any object's constructor. By doing so, that object may have a  stale version of the container or request on subsequent requests.

Octane will automatically handle resetting any first-party framework state between requests. However, Octane does not always know how to reset the global state created by your application. Therefore, you should be aware of how to build your application in a way that is Octane friendly. Below, we will discuss the most common situations that may cause problems while using Octane.

#### Container Injection

In general, you should avoid injecting the application service container or HTTP request instance into the constructors of other objects. For example, the following binding injects the entire application service container into an object that is bound as a singleton:

```php
use App\Service;

/**
 * Register any application services.
 *
 * @return void
 */
public function register()
{
    $this->app->singleton(Service::class, function ($app) {
        return new Service($app);
    });
}
```

In this example, if the `Service` instance is resolved during the application boot process, the container will be injected into the service and that same container will be held by the `Service` instance on subsequent requests. This **may** not be a problem for your particular application; however, it can lead to the container unexpectedly missing bindings that were added later in the boot cycle or by a subsequent request.

As a work-around, you could either stop registering the binding as a singleton, or you could inject a container resolver closure into the service that always resolves the current container instance:

```php
use App\Service;
use Illuminate\Container\Container;

$this->app->bind(Service::class, function ($app) {
    return new Service($app);
});

$this->app->singleton(Service::class, function () {
    return new Service(fn () => Container::getInstance());
});
```

The global `app` helper and the `Container::getInstance()` method will always return the latest version of the application container.

#### Request Injection

In general, you should avoid injecting the application service container or HTTP request instance into the constructors of other objects. For example, the following binding injects the entire request instance into an object that is bound as a singleton:

```php
use App\Service;

/**
 * Register any application services.
 *
 * @return void
 */
public function register()
{
    $this->app->singleton(Service::class, function ($app) {
        return new Service($app['request']);
    });
}
```

In this example, if the `Service` instance is resolved during the application boot process, the HTTP request will be injected into the service and that same request will be held by the `Service` instance on subsequent requests. Therefore, all headers, input, and query string data will be incorrect, as well as all other request data.

As a work-around, you could either stop registering the binding as a singleton, or you could inject a request resolver closure into the service that always resolves the current request instance. Or, the most recommended approach is simply to pass the specific request information your object needs to one of of the object's methods at runtime:

```php
use App\Service;

$this->app->bind(Service::class, function ($app) {
    return new Service($app['request']);
});

$this->app->singleton(Service::class, function ($app) {
    return new Service(fn () => $app['request']);
});

// Or...

$service->method($request->input('name'));
```

The global `request` helper will always return the request the application is currently handling and is therefore safe to use within your application.

**Note:** It is acceptable to type-hint the `Illuminate\Http\Request` instance on your controller methods and route closures.

#### Configuration Repository Injection

In general, you should avoid injecting the configuration repository instance into the constructors of other objects. For example, the following binding injects the configuration repository into an object that is bound as a singleton:

```php
use App\Service;

/**
 * Register any application services.
 *
 * @return void
 */
public function register()
{
    $this->app->singleton(Service::class, function ($app) {
        return new Service($app->make('config'));
    });
}
```

In this example, if the configuration values change between requests, that service will not have access to the new values because it's depending on the original repository instance.

As a work-around, you could either stop registering the binding as a singleton, or you could inject a configuration repository resolver closure to the class:

```php
use App\Service;
use Illuminate\Container\Container;

$this->app->bind(Service::class, function ($app) {
    return new Service($app->make('config'));
});

$this->app->singleton(Service::class, function () {
    return new Service(fn () => Container::getInstance()->make('config'));
});
```

The global `config` will always return the latest version of the configuration repository and is therefore safe to use within your application.

### General Memory Leaks

Remember, Octane keeps your application in memory between requests; therefore, adding data to a statically maintained array will result in a memory leak. For example, the following controller has a memory leak since each request to the application will continue to add data to the static `$data` array:

```php
use App\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Handle an incoming request.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return void
 */
public function index(Request $request)
{
    Service::$data[] = Str::random(10);

    // ...
}
```

### Concurrent Tasks

> **This feature requires [Swoole](#swoole).**

When using Swoole, you may execute operations concurrently via light-weight background tasks. You may accomplish this using Octane's `concurrently` method. You may combine this method with PHP array destructuring to retrieve the results of each operation:

```php
use App\User;
use App\Server;
use Laravel\Octane\Facades\Octane;

[$users, $servers] = Octane::concurrently([
    fn () => User::all(),
    fn () => Server::all(),
]);
```

### Ticks / Intervals

> **This feature requires [Swoole](#swoole).**

When using Swoole, you may register "tick" operations that will be executed every specified number of seconds. You may register "tick" callbacks via the `tick` method. The first argument provided to the `tick` method should be a string that represents the name of the ticker. The second argument should be a callable that will be invoked at the specified interval. In this example, we will register a closure to be invoked every 10 seconds. Typically, the `tick` method should be called within the `boot` method of one of your application's service providers:

```php
Octane::tick('simple-ticker', fn () => ray('Ticking...'))
        ->seconds(10);
```

Using the `immediate` method, you may instruct Octane to immediately invoke the tick callback when the Octane server initially boots, and every N seconds thereafter:

```php
Octane::tick('simple-ticker', fn () => ray('Ticking...'))
        ->seconds(10)
        ->immediate();
```

### The Octane Cache

> **This feature requires [Swoole](#swoole).**

When using Swoole, you may leverage the Octane cache driver, which provides read and write speeds of up to 2 million operations per second. This cache driver is powered by [Swoole tables](https://www.swoole.co.uk/docs/modules/swoole-table). All data stored in the cache is available to all workers on the server. However, the cached data will be flushed when the server is restarted:

```php
Cache::store('octane')->put('framework', 'Laravel', 30);
```

**Note:** The maximum number of entries allowed in the Octane cache may be defined in your application's `octane` configuration file.

#### Cache Intervals

In addition to the typical methods provided by Laravel's cache system, the Octane cache driver features interval based caches. These caches are automatically refreshed at the specified interval and should be registered within the `boot` method of one of your application's service providers. For example, the following cache will be refreshed every five seconds:

```php
use Illuminate\Support\Str;

Cache::store('octane')->interval('random', function () {
    return Str::random(10);
}, seconds: 5)
```

### Tables

> **This feature requires [Swoole](#swoole).**

When using Swoole, you may define and interact with your own arbitrary [Swoole tables](https://www.swoole.co.uk/docs/modules/swoole-table). Swoole tables provide extreme performance throughput and the data in these tables can be accessed by all workers on the server. However, the data within them will be lost when the server is restarted.

Tables should be defined within the `tables` configuration array of your application's `octane` configuration file. An example table that allows a maximum of 1000 rows is already configured for you. The maximum size of string columns may be configured by specifying the column size after the column type as seen below:

```php
'tables' => [
    'example:1000' => [
        'name' => 'string:1000',
        'votes' => 'int',
    ],
],
```

To access a table, you may use the `Octane::table` method:

```php
use Laravel\Octane\Facades\Octane;

Octane::table('example')->set('uuid', [
    'name' => 'Nuno Maduro',
    'votes' => 1000,
]);

return Octane::table('example')->get('uuid');
```

**Note:** The column types supported by Swoole tables are: `string`, `int`, and `float`.

## Contributing

Thank you for considering contributing to Octane! You can read the contribution guide [here](.github/CONTRIBUTING.md).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/laravel/octane/security/policy) on how to report security vulnerabilities.

## License

Laravel Octane is open-sourced software licensed under the [MIT license](LICENSE.md).

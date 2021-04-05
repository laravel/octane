<p align="center"><img src="https://laravel.com/assets/img/components/logo-octane.svg"></p>

<p align="center">
<a href="https://github.com/laravel/octane/actions"><img src="https://github.com/laravel/octane/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/octane"><img src="https://img.shields.io/packagist/dt/laravel/octane" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/octane"><img src="https://img.shields.io/packagist/v/laravel/octane" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/octane"><img src="https://img.shields.io/packagist/l/laravel/octane" alt="License"></a>
</p>

## Introduction

Laravel Octane supercharges your application's performance by serving your application using high-powered application servers, including [Swoole](https://swoole.co.uk) and [RoadRunner](https://roadrunner.dev). Octane boots your application once, keeps it memory, and then feeds it requests at supersonic speeds.

## Documentation

**IMPORTANT: Laravel Octane is within a beta period. It should only be used for local development and testing in order to improve the quality of the library and resolve any existing bugs. We are still in the process of ensuring Octane compatibility with all first-party Laravel packages.**

### Package Support

We are in the process of updating our first-party packages to ensure Octane compatibility. You can find a table of our progress below. You must be using the latest tagged release of these libraries in order to receive Octane compatibility:

Package | Status
------------ | -------------
Breeze | ✅ Operational
Cashier | ✅ Operational
Dusk | ✅ Operational
Fortify | ✅ Operational
Horizon UI | ✅ Operational
Jetstream Inertia | ✅ Operational
Jetstream Livewire | ✅ Operational
Nova | 👷‍♀️ In Progress
Passport | ❓ Unknown
Sanctum | ✅ Operational
Scout | ✅ Operational
Socialite | ✅ Operational
Spark | ❓ Unknown
Telescope | ✅ Operational

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

#### Swoole

If you plan to use the Swoole application server to serve your Laravel Octane application, you must install the Swoole PHP extension. Typically, this can be done via PECL:

```bash
pecl install swoole
```

#### Swoole Via Laravel Sail

Alternatively, you may develop your Swoole based Octane application using [Laravel Sail](https://laravel.com/docs/sail), the official Docker based development environment for Laravel. Laravel Sail includes the Swoole extension by default. However, you will still need to adjust the `supervisor.conf` file used by Sail to keep your application running. To get started, execute the `sail:publish` Artisan command:

```bash
php artisan sail:publish
```

Next, update the `command` directive of your application's `docker/supervisord.conf` file so that Sail serves your application using Octane instead of the PHP development server:

```ini
command=/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan octane:start --server=swoole --host=0.0.0.0 --port=80
```

Next, build your Sail images:

```bash
./vendor/bin/sail build
```

#### RoadRunner

RoadRunner is powered by the RoadRunner binary, which is built using Go. The first time you start a RoadRunner based Octane server, Octane will offer to download and install the RoadRunner binary for you.

### Serving Your Application

The Octane server can be started via the `octane:start` Artisan command. By default, this command will utilize the server specified by the `server` configuration option of your application's `octane` configuration file:

```bash
php artisan octane:start
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

To help prevent stray memory leaks, Octane can gracefully restart a worker once it has handled a given number of requests. To instruct Octane to do this, you may use the `--max-request` option:

```bash
php artisan octane:start --max-requests=250
```

#### Reloading The Application Workers

You may gracefully restart the Octane server's application workers using the `octane:reload` command. Typically, this should be done after deployment:

```bash
php artisan octane:reload
```

### Dependency Injection & Octane

Since Octane boots your application once and keeps it in memory while serving requests, there are a few caveats you should consider while building your application. For example, the `register` and `boot` methods of your application's service providers will only be executed once when the request worker initially boots. On subsequent requests, the same application instance will be reused.

In light of this, you should take special care when injecting the application service container or request into any object's constructor. By doing so, that object may have a  stale version of the container or request on subsequent requests.

Octane will automatically handle resetting any first-party framework state between requests. However, Octane does not always know how to reset global state created by your application. Therefore, you should be aware of how to build your application in a way that is Octane friendly. Below, we will discuss the most common situations that may cause problems while using Octane.

#### Container Injection

In general, you should avoid injecting the application service container or HTTP request instance into the constructors of other objects. For example, the following binding injects the entire application service container into an object that is bound as a singleton:

```php
use App\Service;

/**
 * Register any application services.
 *
 * @return void
 */
public function boot()
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

#### Request Injection

In general, you should avoid injecting the application service container or HTTP request instance into the constructors of other objects. For example, the following binding injects the entire request instance into an object that is bound as a singleton:

```php
use App\Service;

/**
 * Register any application services.
 *
 * @return void
 */
public function boot()
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

**Note:** It is acceptable to type-hint the `Illuminate\Http\Request` instance on your controller methods and route closures.

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

When using Swoole, you may register "tick" operations that will be executed every specified number of seconds. You may register "tick" callbacks via the `tick` method. The first argument provided to the `tick` method should be a string that represents the name of the ticker. The second argument should be a callable that will be invoked at the specified interval. In this example, we will register a closure to be invoked every 10 seconds:

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

When using Swoole, you may leverage the Octane cache driver, which provides read and write speeds of up to 2 million operations per second. This cache driver is powered by [Swoole tables](https://www.swoole.co.uk/docs/modules/swoole-table). All data stored in the cache is available to all workers on the server. However, the cached data will be flushed when the server is restarted:

```php
Cache::store('octane')->put('framework', 'Laravel', 30);
```

**Note:** The maximum number of entries allowed in the Octane cache may be defined in your application's `octane` configuration file.

#### Cache Intervals

In addition to the typical methods provided by Laravel's cache system, the Octane cache driver features interval based caches. These caches are automatically refreshed at the specified interval. For example, the following cache will be refreshed every five seconds:

```php
use Illuminate\Support\Str;

Cache::store('octane')->interval('random', function () {
    return Str::random(10);
}, seconds: 5)
```

### Tables

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

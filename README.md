<p align="center"><img src="https://laravel.com/assets/img/components/logo-octane.svg"></p>

<p align="center">
<a href="https://github.com/laravel/octane/actions"><img src="https://github.com/laravel/octane/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/octane"><img src="https://img.shields.io/packagist/dt/laravel/octane" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/octane"><img src="https://img.shields.io/packagist/v/laravel/octane" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/octane"><img src="https://img.shields.io/packagist/l/laravel/octane" alt="License"></a>
</p>

## Introduction

Laravel Octane supercharges your application's performance by serving your application using high-powered application servers, including [Swoole](https://swoole.co.uk) and [RoadRunner](https://roadrunner.dev).

## Documentation

### Installation

Octane may be installed via the Composer package manager:

```bash
composer require laravel/octane
```

After installing Octane, you should publish its configuration file using the `vendor:publish` Artisan command:

```bash
php artisan vendor:publish --tag=octane-config
```

### Server Prerequisites

#### Swoole

If you plan to use the Swoole application server to serve your Laravel Octane application, you must install the Swoole PHP extension. Typically, this can be done via PECL:

```bash
pecl install swoole
```

Alternatively, you may use [Laravel Sail](https://laravel.com/docs/sail), the official Docker based development environment for Laravel. Laravel Sail includes the Swoole extension by default.

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

**Note:** It is acceptable to type-hint the `Illuminate\Http\Request` instance on your controller methods and routes closures.

### General Memory Leaks

Remember, Octane keeps your application in memory between requests; therefore, adding data to a statically maintained array will result in a memory leak. For example, the following controller has a memory leak. Each request to the application will continue to add data to the static `$data` array:

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

## Contributing

Thank you for considering contributing to Octane! You can read the contribution guide [here](.github/CONTRIBUTING.md).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/laravel/octane/security/policy) on how to report security vulnerabilities.

## License

Laravel Octane is open-sourced software licensed under the [MIT license](LICENSE.md).

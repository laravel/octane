# State Isolation in Laravel Octane

> **Applies to:** All drivers (Swoole, RoadRunner, FrankenPHP)

## Why State Isolation Matters

In Octane, the application container is booted **once** and reused across every request. Any state stored in a singleton or static property during request A is still there during request B, C, and beyond. This is the most common source of bugs in Octane applications.

## Dangerous Patterns

### Singleton State Bleed
```php
// BAD — $user is set on request 1 and leaks into request 2
class UserContext
{
    public ?User $user = null;
}

// Registered as a singleton:
$this->app->singleton(UserContext::class);
```

### Static Property Bleed
```php
// BAD — static properties persist across requests
class RequestCounter
{
    public static int $count = 0;
}
```

### Resolved Instances Holding Request Data
```php
// BAD — resolved once, holds reference to first request's auth
$this->app->singleton(PaymentService::class, function ($app) {
    return new PaymentService($app->make('auth')->user());
});
```

## Safe Patterns

### Use Scoped Bindings (Recommended)
Scoped bindings are flushed and re-resolved at the start of each request:
```php
// GOOD — new instance per request
$this->app->scoped(UserContext::class);
```

### Resolve Dependencies Lazily
```php
// GOOD — auth resolved per-call, not at binding time
$this->app->singleton(PaymentService::class, function ($app) {
    return new PaymentService(fn() => $app->make('auth')->user());
});
```

### Reset Static State in a Listener
If you must use static state, reset it using Octane's `RequestHandled` or `RequestTerminated` event:
```php
use Laravel\Octane\Events\RequestHandled;

Event::listen(RequestHandled::class, function () {
    RequestCounter::$count = 0;
});
```

## Octane's Built-in Isolation (warm/flush)

Octane automatically flushes or re-binds certain core framework services between requests. You can configure additional services in `config/octane.php`:

```php
'warm' => [
    // Pre-resolve these on worker boot
    ...Octane::defaultServicesToWarm(),
],

'flush' => [
    // Re-bind these on every request
    App\Services\MyRequestAwareService::class,
],
```

**Rule:** If a service stores any data derived from `$request`, `Auth::user()`, or any per-request value, it must be scoped, flushed, or resolved lazily.

## Middleware and Request Pipeline

The `Request` object itself is always fresh per request — Octane replaces it in the container. However, any service that captured the old `Request` at construction time will hold a stale reference.

Always inject `Request` through method parameters or use `request()` helper inside methods, not at class construction.

## Listeners Octane Registers Automatically

Octane registers the following listeners to flush core framework state between requests:
- `FlushAuthenticationState`
- `FlushQueuedCookies`
- `FlushSessionState`
- `FlushTranslatorCache`
- `FlushLogContext`
- `GiveNewApplicationInstanceToAuthorizationGate`
- `GiveNewApplicationInstanceToHttpKernel`
- `GiveNewApplicationInstanceToRouter`
- And many more (see `OctaneServiceProvider::$requestLifecycleListeners`)

These cover the framework core. Your application code is your responsibility.

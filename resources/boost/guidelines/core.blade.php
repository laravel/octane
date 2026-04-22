# Octane

## Architecture & Request Lifecycle

- Octane boots the application once and reuses it across requests, so singletons persist between requests.
- Each request is handled by a worker process; the application state is shared within the same worker.
- On each new request, Octane fires `RequestReceived` and prepares a fresh application sandbox via listeners.
- On task or tick operations, Octane fires `TaskReceived` / `TickReceived` to reset the operation sandbox.
- Use `octane:start` / `octane:stop` / `octane:reload` / `octane:status` Artisan commands to manage the server.

## Dependency Injection & Singletons

- The Laravel container's `scoped` method may be used as a safe alternative to `singleton`.
- Never inject the container, request, or config repository into a singleton's constructor; use a resolver closure or `bind()` instead:

```php
// Bad
$this->app->singleton(Service::class, fn (Application $app) => new Service($app['request']));

// Good
$this->app->singleton(Service::class, fn () => new Service(fn () => request()));
```

- Never append to static properties, as they accumulate in memory across requests.
- Services that hold per-request state should be registered with `scoped()` so they are re-created each request.
- Add custom `scoped` bindings to `config/octane.php`'s `flush` array to ensure they are reset between requests.

## Server Drivers

- Three server drivers are supported: `swoole`, `roadrunner`, and `frankenphp`.
- The active server is configured via `OCTANE_SERVER` in `.env` (default: `roadrunner`).
- RoadRunner requires the `spiral/roadrunner` package and the `rr` binary; configured via `.rr.yaml`.
- Swoole requires the `swoole` PHP extension; enables native coroutines, tasks, and shared memory tables.
- FrankenPHP is a modern PHP app server built on Caddy; supports worker mode and HTTP/3.
- Do not assume a specific server driver in application code; use driver-agnostic abstractions where possible.
- Server-specific features (e.g., Swoole tables, Swoole tasks) should be conditionally used only when the driver is Swoole.

## Concurrency (Swoole Only)

- `Octane::concurrently()` dispatches tasks as Swoole background tasks and resolves their results in parallel.
- Pass an array of callables with named keys; results are returned keyed by those same keys.
- If a task does not complete within `$waitMilliseconds` (default 3000 ms), its value is `false`.
- `Octane::concurrently()` is a no-op (sequential) outside Swoole; it is safe to call on all drivers.

```php
[$users, $orders] = array_values(Octane::concurrently([
    'users'  => fn () => User::all(),
    'orders' => fn () => Order::all(),
], waitMilliseconds: 5000));
```

## Swoole Tables (Swoole Only)

- Swoole tables are in-memory hash maps shared across all worker processes.
- Define tables in `config/octane.php` under `tables`: key is `name:rows`, value is column definitions.
- Column types: `int`, `float`, `string:N` (N = max byte length).
- Access a table via `Octane::table('name')`; throws if Swoole server is not running.
- Swoole tables persist data across requests and workers but are lost when the server restarts.

```php
// config/octane.php
'tables' => [
    'visitors:1000' => [
        'ip'      => 'string:45',
        'count'   => 'int',
        'visited' => 'float',
    ],
],

// Usage
Octane::table('visitors')->set('127.0.0.1', ['ip' => '127.0.0.1', 'count' => 1, 'visited' => microtime(true)]);
$row = Octane::table('visitors')->get('127.0.0.1');
```

## Octane Cache (Swoole Only)

- Octane provides an in-memory cache store backed by a Swoole table: `Cache::store('octane')`.
- Configure maximum rows and bytes-per-row in `config/octane.php` under `cache`.
- This cache store does not persist across server restarts and is not shared across servers.
- Use it for ephemeral, high-frequency, low-latency data that fits within the configured size.

## Tick Handlers (Swoole Only)

- `Octane::tick(key, callback, seconds, immediate)` registers a recurring callback fired every N seconds.
- Ticks are Swoole timer-based; they only run when using the Swoole driver.
- The tick callback receives no arguments; use closures to capture dependencies safely.
- Register ticks in a service provider's `boot` method, not in request-scoped code.

```php
Octane::tick('stats', fn () => Cache::put('stats', Stats::compile(), 60), seconds: 5);
```

## Warming & Flushing Services

- `config/octane.php`'s `warm` array lists service class names that are pre-resolved when a worker boots.
- `config/octane.php`'s `flush` array lists bindings that are flushed (cleared) before each new request.
- Use `Octane::defaultServicesToWarm()` to include the built-in set of warmed services.
- Services in `warm` should be stateless or designed for long-lived use; do not warm per-request state.

## Request & State Isolation

- Octane automatically resets authentication state, sessions, queued cookies, locale state, log context, database query log, Str cache, translator cache, Vite state, and uploaded files between requests via built-in listeners.
- Third-party packages that store per-request state (e.g., Inertia, Livewire, Scout, Socialite) have dedicated Octane listeners: `PrepareInertiaForNextOperation`, `PrepareLivewireForNextOperation`, etc.
- For custom stateful singletons, implement an `OctaneOperationTerminated` listener or add them to the `flush` config array.
- Managers (CacheManager, DatabaseManager, etc.) receive a fresh application instance per request via `GiveNewApplicationInstanceTo*` listeners.
- Avoid storing request-specific data in class properties of singletons; prefer closures that lazily resolve the request.

## Event System

Octane dispatches the following events (listen to them via `config/octane.php`'s `listeners` array):

- `WorkerStarting` — fired when a worker process boots; use for one-time setup.
- `RequestReceived` — fired before handling each HTTP request; use to prepare per-request state.
- `RequestHandled` — fired after a response is generated but before it is sent.
- `RequestTerminated` — fired after the response is sent; use for cleanup.
- `TaskReceived` — fired before a concurrent task runs (Swoole).
- `TaskTerminated` — fired after a concurrent task completes (Swoole).
- `TickReceived` — fired before a tick callback runs (Swoole).
- `TickTerminated` — fired after a tick callback completes (Swoole).
- `OperationTerminated` — fired after any operation (request, task, or tick) completes; use `FlushOnce` and `FlushTemporaryContainerInstances` here.
- `WorkerErrorOccurred` — fired on unhandled worker errors; use `StopWorkerIfNecessary` here.
- `WorkerStopping` — fired when a worker gracefully shuts down.

## Memory Management

- Set `garbage` in `config/octane.php` (MB) to trigger automatic `gc_collect_cycles()` when memory exceeds the threshold (default: 50 MB).
- Add `CollectGarbage::class` to the `OperationTerminated` listeners if you need deterministic GC after each operation.
- Add `DisconnectFromDatabases::class` to `OperationTerminated` listeners if long-running tasks cause stale DB connections.
- Unset large variables when they are no longer needed within long-running operations.
- Do not cache Eloquent model collections in singletons unless they are explicitly refreshed per request.

## HTTP & HTTPS Configuration

- Set `OCTANE_HTTPS=true` when serving behind an HTTPS termination proxy so `url()` helpers generate correct `https://` URLs.
- Octane handles `X-Forwarded-For`, `X-Forwarded-Host`, and `X-Forwarded-Proto` headers via the `EnforceRequestScheme` listener.

## File Watching & Hot Reload

- Run `php artisan octane:start --watch` to automatically reload workers when files change (requires `chokidar`).
- Configure watched paths in `config/octane.php` under `watch`; glob patterns are supported.
- The `octane:reload` command sends a POSIX signal to gracefully reload workers without downtime.

## Maximum Execution Time

- Configure `max_execution_time` in `config/octane.php` (seconds); set to `0` for no limit.
- Requests exceeding the limit are terminated with a 500 response to prevent worker lock-up.

## Testing

- Use `Octane::fake()` in tests to simulate Octane's concurrent task dispatcher without a real server.
- `Octane::assertDispatchedConcurrently()` and similar assertions are available after calling `Octane::fake()`.
- Feature tests run via the standard Laravel test client and do not require Octane to be running.

## Common Pitfalls

- Do not store references to the `Request` object in singletons or static variables; always resolve it fresh via `request()` or injection into controller/route handlers.
- Do not use `app()->make()` at class construction time for request-scoped services in singletons; use a `Closure` factory instead.
- Be careful with Laravel `once()` helper — `FlushOnce::class` resets its cache between operations automatically.
- Do not register `tick()` handlers inside request handlers; register them once in a service provider.
- Eloquent model events and observers share the same singleton instance; ensure they do not accumulate listeners across requests.
- PHP `session_start()` and native PHP sessions are not compatible with Octane; always use Laravel's session driver.

# Octane

{{-- Detect the active Octane server driver from config --}}
@php $octaneDriver = config('octane.server', 'unknown'); @endphp

## Driver Detection

The active server driver is readable at runtime via:

```php
// Primary: config value ('swoole', 'roadrunner', 'frankenphp')
$driver = config('octane.server');

// Swoole runtime check (bound only while Swoole server is active)
$isSwoole = class_exists('Swoole\\Http\\Server') && app()->bound('Swoole\\Http\\Server');

// FrankenPHP runtime check
$isFrankenPhp = function_exists('frankenphp_handle_request');

// RoadRunner runtime check (sets RR_MODE env variable)
$isRoadRunner = getenv('RR_MODE') === 'http';
```

## Driver Capability Matrix

| Feature                  | Swoole / Open Swoole | RoadRunner | FrankenPHP |
|--------------------------|:--------------------:|:----------:|:----------:|
| Concurrent tasks         | ✅ (task workers)    | ❌         | ❌         |
| Tick / interval handlers | ✅                   | ❌         | ❌         |
| Swoole Tables            | ✅                   | ❌         | ❌         |
| Octane Cache driver      | ✅ (Swoole-backed)   | ⚠️ (array) | ⚠️ (array) |
| HTTP/2 & HTTP/3          | ❌                   | ❌         | ✅         |
| Early hints              | ❌                   | ❌         | ✅         |
| Custom Caddyfile         | ❌                   | ❌         | ✅         |
| Go binary worker model   | ❌                   | ✅         | ❌         |
| PSR-7 middleware         | ❌                   | ✅         | ❌         |
| Task workers (--task-workers) | ✅             | ❌         | ❌         |
| File watching (--watch)  | ✅                   | ✅         | ✅         |
| Max request recycling    | ✅                   | ✅         | ✅         |

---

## Architecture & Request Lifecycle (All Drivers)

- Octane boots the application once and reuses it across requests; singletons persist between requests
- Each request is handled by a worker process; state is shared within the same worker
- On each new request, Octane fires `RequestReceived` and prepares a fresh application sandbox
- On task/tick operations, Octane fires `TaskReceived` / `TickReceived` to reset the operation context
- Use `octane:start` / `octane:stop` / `octane:reload` / `octane:status` Artisan commands to manage the server
- `octane:start --workers=N` sets the number of worker processes (default: CPU core count)
- `octane:start --max-requests=N` recycles workers after N requests (default: 500) to prevent memory leaks

## Dependency Injection & Singletons (All Drivers)

- Never inject the container, request, or config repository into a singleton's constructor; they will be stale
- Use closures, `Container::getInstance()`, or `app()` / `request()` / `config()` helpers inside methods instead
- The Laravel container's `scoped()` method may be used as a safe alternative to `singleton()`
- Services that hold per-request state should be registered with `scoped()` so they are re-created per request
- Add custom `scoped` bindings to `config/octane.php`'s `flush` array to ensure they are reset between requests

```php
// Bad - stale request injected at boot time
$this->app->singleton(Service::class, fn (Application $app) => new Service($app['request']));

// Good - resolve request lazily per call
$this->app->singleton(Service::class, fn () => new Service(fn () => request()));

// Also Good - use scoped() binding
$this->app->scoped(Service::class, fn (Application $app) => new Service($app['request']));
```

## State Isolation & Built-in Listeners (All Drivers)

Octane registers 30+ built-in listeners that reset framework state between requests, including:
- Auth, session, cookies, queued cookies
- Database connections (disconnected and reconnected)
- Config sandbox, URL generator sandbox
- Router, mail, broadcast, log, notification, queue, validation, view managers
- Inertia, Livewire, Scout, Socialite preparation
- Garbage collection, exception reporting, worker stop signals

To reset custom services between requests, add them to `config/octane.php`:
```php
'flush' => [
    App\Services\MyService::class,
],
'warm' => [
    App\Services\ExpensiveService::class, // Pre-resolved at worker boot
],
```

## Octane Events (All Drivers)

Nine lifecycle events are available for listeners:
- `RequestReceived` - fired before each HTTP request is handled
- `RequestHandled` - fired after HTTP response is sent
- `RequestTerminated` - fired after request/response lifecycle ends
- `TaskReceived` - fired before a concurrent task executes (Swoole)
- `TaskTerminated` - fired after a concurrent task completes (Swoole)
- `TickReceived` - fired before a tick callback executes (Swoole)
- `TickTerminated` - fired after a tick callback completes (Swoole)
- `WorkerStarting` - fired when a worker process starts
- `WorkerErrorOccurred` - fired when an unhandled error occurs in a worker

## Memory Management (All Drivers)

- Octane recycles workers after `--max-requests` (default 500) to prevent memory exhaustion
- Avoid appending to static properties or global state across requests
- `config/octane.php` `garbage` section controls garbage collection (default: every 50 requests)
- Workers run in long-lived PHP processes; any global mutation persists until the worker is recycled

## HTTP / HTTPS & Nginx (All Drivers)

- Set `OCTANE_HTTPS=true` in `.env` (or `'https' => true` in `config/octane.php`) to generate `https://` links
- In production, proxy Octane behind Nginx on port 8000 using `proxy_pass http://127.0.0.1:8000`
- Use Supervisor (`octane:start --server=DRIVER --host=127.0.0.1 --port=8000`) for process management

## File Watching & Hot Reload (All Drivers)

- Use `octane:start --watch` in development to auto-reload workers on file changes
- Requires Node.js and `chokidar` (`npm install --save-dev chokidar`)
- Configure watched paths in the `watch` key in `config/octane.php`

## Max Execution Time (All Drivers)

- Default max execution time is 30 seconds per request (`config/octane.php` `max_execution_time`)
- Long-running jobs should be moved to queued jobs, not handled inline in HTTP requests
- Restart the Octane server after changing `max_execution_time`

## Testing (All Drivers)

```php
use Laravel\Octane\Facades\Octane;

Octane::fake(); // Mocks concurrent tasks and ticks for unit tests

$this->artisan('octane:start'); // Use for integration tests
```

---

{{-- ============================================================ --}}
{{-- DRIVER-SPECIFIC SECTIONS — Only the active driver is shown  --}}
{{-- ============================================================ --}}

@if(in_array($octaneDriver, ['swoole', 'openswoole']))
## Swoole / Open Swoole — Driver-Specific Features

**This application is using the Swoole server** (`config/octane.server = '{{ $octaneDriver }}'`).

### Concurrency — `Octane::concurrently()`

Executes multiple closures in parallel using Swoole task workers. Requires `--task-workers` to be set.

```php
use Laravel\Octane\Facades\Octane;

[$users, $servers] = Octane::concurrently([
    fn () => User::all(),
    fn () => Server::all(),
]);
```

- Each closure runs in a separate task worker process with its own application instance
- Maximum 1024 tasks per `concurrently()` call (Swoole limitation)
- Start task workers: `octane:start --workers=4 --task-workers=6`
- Task workers do NOT share state with the main worker

### Tick / Interval Handlers

Register recurring closures that fire every N seconds in the background:

```php
// In a ServiceProvider boot() method
Octane::tick('cache-warmer', fn () => Cache::put('key', expensive(), 60))
    ->seconds(30)
    ->immediate(); // Also run once immediately on server start
```

- Tick handlers run in the main worker, not task workers
- `TickReceived` / `TickTerminated` events fire around each tick
- Name collisions on `tick()` will overwrite the previous handler with that name

### Swoole Tables (Shared Memory)

Swoole tables are fixed-size, in-memory hash maps shared across ALL workers. Ultra-fast (no serialization).

Define in `config/octane.php`:
```php
'tables' => [
    'users:1000' => [    // key: table_name:max_rows
        'name'   => 'string:200',  // type:max_bytes
        'votes'  => 'int',
        'score'  => 'float',
    ],
],
```

Access at runtime:
```php
use Laravel\Octane\Facades\Octane;

Octane::table('users')->set($uuid, ['name' => 'Alice', 'votes' => 10]);
$row = Octane::table('users')->get($uuid);
Octane::table('users')->del($uuid);
```

- Column types: `string`, `int`, `float` only
- Table size is fixed at definition time; cannot be resized at runtime
- Tables are destroyed when the Swoole server stops
- `Octane::table()` throws if called outside a Swoole context

### Octane Cache Driver (Swoole-backed)

When Swoole is active, `Cache::store('octane')` is backed by a Swoole table (shared across workers, in-memory):

```php
Cache::store('octane')->put('framework', 'Laravel', ttl: 30);
Cache::store('octane')->get('framework');

// Interval cache — auto-refreshed on every tick
Cache::store('octane')->interval('random', fn () => Str::random(), seconds: 5);
```

- Configure max entries via `config/octane.php` `cache` section
- Data is shared across all Swoole workers (no per-worker isolation)
- Falls back to in-memory array store if not on Swoole (`OctaneArrayStore`)

### Swoole-Specific Config (`config/octane.php`)

```php
'swoole' => [
    'options' => [
        'log_file'           => storage_path('logs/swoole_http.log'),
        'package_max_length' => 1024 * 1024 * 2,  // 2MB max request body
        'socket_buffer_size' => 1024 * 1024 * 128, // 128MB socket buffer
        'max_coroutine'      => 3000,              // Max coroutines per worker
    ],
],
```

All `Swoole\Server` options are supported under `swoole.options`.

### Coroutine Dispatcher

When Swoole is present, `DispatchesCoroutines` is resolved as `SwooleCoroutineDispatcher` (true coroutines).
Without Swoole, it falls back to `SequentialCoroutineDispatcher` (runs tasks sequentially).

@elseif($octaneDriver === 'frankenphp')
## FrankenPHP — Driver-Specific Features

**This application is using the FrankenPHP server** (`config/octane.server = 'frankenphp'`).

### Key Capabilities

- Built-in HTTP/2 and HTTP/3 support (no additional Nginx/Caddy config needed)
- Early hints (103 status) support out of the box
- Written in Go; includes a bundled Caddy web server
- No task workers, no `Octane::concurrently()`, no Swoole tables or ticks

### Starting FrankenPHP

```bash
php artisan octane:start --server=frankenphp --host=localhost --port=8000
php artisan octane:frankenphp  # Dedicated command
```

### Custom Caddyfile

Customize FrankenPHP's underlying Caddy configuration:
```bash
php artisan octane:start --server=frankenphp --caddyfile=/path/to/Caddyfile
```

See [Caddy documentation](https://caddyserver.com/docs/caddyfile) for all Caddyfile options.

### Native Logger

Pass `--log-level` to activate FrankenPHP's native Go logger instead of Laravel's logger:
```bash
php artisan octane:start --server=frankenphp --log-level=debug
```

### Docker Setup

```dockerfile
FROM dunglas/frankenphp
RUN install-php-extensions pcntl
COPY . /app
ENTRYPOINT ["php", "artisan", "octane:frankenphp"]
```

Development Compose:
```yaml
services:
  frankenphp:
    build: .
    entrypoint: php artisan octane:frankenphp --workers=1 --max-requests=1
    ports: ["8000:8000"]
    volumes: [".:/app"]
```

### HTTPS / HTTP3 with Sail

```yaml
# docker-compose.yml
services:
  laravel.test:
    ports:
      - '443:443'
      - '443:443/udp'    # Required for HTTP/3 over QUIC
    environment:
      SUPERVISOR_PHP_COMMAND: >
        /usr/bin/php artisan octane:start --host=localhost --port=443
        --admin-port=2019 --https --http3
```

Access via `https://localhost` (not `https://127.0.0.1` — known FrankenPHP Docker issue).

### Octane Cache on FrankenPHP

`Cache::store('octane')` falls back to `OctaneArrayStore` (per-worker, not shared) since there is no Swoole table.
Use Redis or another shared cache driver for cross-worker data on FrankenPHP.

### Runtime Detection

```php
if (function_exists('frankenphp_handle_request')) {
    // Running inside FrankenPHP
}
```

@elseif($octaneDriver === 'roadrunner')
## RoadRunner — Driver-Specific Features

**This application is using the RoadRunner server** (`config/octane.server = 'roadrunner'`).

### Key Capabilities

- Go-based binary worker model — each PHP worker is a long-lived process managed by the Go binary
- Standard sequential request handling — no coroutines or task workers
- PSR-7 request/response pipeline
- No Swoole tables, no `Octane::concurrently()`, no tick handlers

### Installation

```bash
composer require laravel/octane spiral/roadrunner-cli spiral/roadrunner-http
./vendor/bin/rr get-binary   # Downloads the latest RoadRunner Go binary
```

### Starting RoadRunner

```bash
php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000
php artisan octane:roadrunner  # Dedicated command
```

### RoadRunner Configuration (`.rr.yaml`)

RoadRunner uses a YAML config file (`.rr.yaml`) in the project root for advanced settings:
```yaml
server:
  command: "php artisan octane:start --server=roadrunner"

http:
  address: "0.0.0.0:8000"
  pool:
    num_workers: 4
    max_jobs: 500       # Recycle worker after N requests

logs:
  mode: production
  level: warn
```

### PSR-7 Middleware

RoadRunner uses PSR-7 requests/responses. Octane bridges these to Laravel's `Illuminate\Http\Request`.
Third-party PSR-7 middleware can be used in the RoadRunner pipeline.

### Octane Cache on RoadRunner

`Cache::store('octane')` falls back to `OctaneArrayStore` (per-worker, NOT shared across workers).
Use Redis or Memcached for any data that needs to be shared across RoadRunner workers.

### Runtime Detection

```php
if (getenv('RR_MODE') === 'http') {
    // Running inside RoadRunner
}
```

### Sail Setup

```yaml
# docker-compose.yml
services:
  laravel.test:
    environment:
      SUPERVISOR_PHP_COMMAND: >
        /usr/bin/php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000
```

Ensure the `./rr` binary is executable: `chmod +x ./rr`

@else
## Server Driver: Unknown

The `config/octane.server` value is not set or unrecognized (current: `{{ $octaneDriver }}`).
Please set `OCTANE_SERVER` in `.env` to one of: `swoole`, `openswoole`, `roadrunner`, `frankenphp`.

Available drivers and their exclusive features:
- `swoole` / `openswoole`: Concurrent tasks, tick handlers, Swoole tables, Octane cache (shared)
- `frankenphp`: HTTP/2, HTTP/3, early hints, custom Caddyfile, Docker-first
- `roadrunner`: Go binary, PSR-7, `.rr.yaml` config, stable process model

@endif

---

## Common Pitfalls (All Drivers)

- **Static property accumulation**: Static arrays/objects grow across requests and are never reset; use instance properties instead
- **Singleton state bleed**: Any mutable state stored in a singleton will persist across requests for that worker
- **Closure-captured stale dependencies**: Closures passed to `singleton()` can capture outdated instances; always resolve lazily
- **Database connections not returned to pool**: Octane disconnects DB connections at `RequestTerminated` but long-running tasks may exhaust the pool; increase `database.connections.*.pool` or use queued jobs
- **File uploads in Octane**: Uploaded files may have permissions issues between requests; `EnsureUploadedFilesAreValid` and `EnsureUploadedFilesCanBeMoved` listeners handle this automatically
- **Swoole-only APIs in non-Swoole context**: `Octane::table()` and `Octane::concurrently()` throw exceptions if called when not running on Swoole; guard with `app()->bound('Swoole\\Http\\Server')`
- **Max request count vs. memory leaks**: Lower `--max-requests` (e.g., 100-250) if you observe memory growth; this trades startup overhead for memory stability

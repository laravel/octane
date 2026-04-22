# Memory Management in Laravel Octane

> **Applies to:** All drivers (Swoole, RoadRunner, FrankenPHP)

## Why Memory Management Matters

In traditional PHP-FPM, each request starts a fresh process and memory is freed automatically when the process ends. In Octane, workers are **long-running** — memory that isn't explicitly freed accumulates over time. Unchecked, this causes memory bloat, degraded performance, and eventually worker crashes.

## Common Memory Leak Sources

### 1. Event Listeners Accumulating
Laravel's event dispatcher holds references to listeners. If you add listeners inside request handlers (rather than service providers), they accumulate:

```php
// BAD — adds a new listener on every request
Route::get('/orders', function () {
    Event::listen(OrderShipped::class, fn() => Log::info('shipped'));
    return Order::all();
});

// GOOD — register listeners in a service provider (once, at boot)
class OrderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderShipped::class, SendShipmentNotification::class);
    }
}
```

### 2. Static Arrays Growing Unbounded
```php
// BAD — grows forever across requests
class RequestLogger
{
    public static array $log = [];

    public static function record(string $message): void
    {
        static::$log[] = $message; // Never pruned
    }
}
```

### 3. Circular References and Closures
Closures that capture large objects (e.g., Eloquent models) and are stored in long-lived containers:
```php
// BAD — closure captures $model; singleton holds it forever
$this->app->singleton('processor', function () use ($model) {
    return fn() => $model->process();
});
```

### 4. Queued Listeners Piling Up
If you dispatch many events in a tight loop without workers processing them, the in-memory queue can grow. Prefer database/Redis queue drivers over `sync` for Octane apps.

## Mitigation Strategies

### Use max-requests to Recycle Workers

Configure workers to restart after handling N requests. This is the most effective safety net:

**FrankenPHP (Caddyfile):**
```caddyfile
php_server {
    worker ../artisan octane:frankenphp-worker --max-requests=500
}
```

**Swoole (config/octane.php):**
```php
'max_requests' => 500,
```

**RoadRunner (.rr.yaml):**
```yaml
http:
  pool:
    max_jobs: 500
```

### Monitor Memory with Octane::memoryUsage()
```php
// In a middleware or tick handler
if (memory_get_usage(true) > 128 * 1024 * 1024) { // 128 MB
    Log::warning('Worker memory high', ['bytes' => memory_get_usage(true)]);
}
```

### Use the flush List for Large Services

Register services that accumulate data in the `flush` list in `config/octane.php`:
```php
'flush' => [
    App\Services\ReportBuilder::class,
    App\Services\DataAggregator::class,
],
```

Flushed services are re-resolved fresh on every request.

### Prune Static State with Request Lifecycle Listeners

```php
use Laravel\Octane\Events\RequestHandled;

class ResetStaticCaches
{
    public function handle(RequestHandled $event): void
    {
        SomeClass::$cache = [];
        AnotherClass::$instances = [];
    }
}
```

Register in `config/octane.php`:
```php
'listeners' => [
    RequestHandled::class => [
        ResetStaticCaches::class,
    ],
],
```

## Memory Profiling

For diagnosing leaks, use Xdebug (memory profiling mode) or Blackfire. The key metric is memory growth **per request** — if memory grows after each request but stabilizes, you likely have static accumulation. If it grows without stabilizing, you have a true leak (e.g., circular references preventing GC).

```bash
# Quick check — watch memory per request
php artisan octane:start --server=swoole &
watch -n1 "curl -s localhost:8000 && ps aux | grep octane"
```

## Garbage Collection

PHP's garbage collector (GC) handles most circular references automatically, but it only runs periodically. In Octane workers, you can force a GC cycle in a tick handler if memory pressure is high:

```php
// Swoole only
Octane::tick('gc', function () {
    if (memory_get_usage(true) > 64 * 1024 * 1024) {
        gc_collect_cycles();
    }
})->seconds(30);
```

## Rules Summary

- **Always set `max-requests`** — this is your last line of defense against unchecked leaks
- **Never append to static arrays in request handlers** — clear them in lifecycle listeners
- **Avoid adding event listeners in controllers or middleware** — use service providers
- **Flush large aggregator services** via the `flush` config
- **Monitor worker memory** in production with application metrics or process monitoring

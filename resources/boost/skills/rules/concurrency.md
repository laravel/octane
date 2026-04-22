# Concurrent Tasks in Laravel Octane

> **Applies to:** Swoole and OpenSwoole drivers only
> RoadRunner and FrankenPHP do not support `Octane::concurrently()`.

## Overview

`Octane::concurrently()` runs multiple closures in parallel using Swoole task workers. Each closure runs in a separate worker process, giving true parallelism for I/O-bound work.

```php
use Laravel\Octane\Facades\Octane;

[$users, $orders] = Octane::concurrently([
    fn () => User::all(),
    fn () => Order::pending()->get(),
]);
```

## How It Works

- Each closure is dispatched to a **task worker** (separate from request workers)
- Task workers share the same bootstrapped application but handle one task at a time
- Results are returned as an array in the same order as the input closures
- Default timeout: **1 second** — long-running tasks will fail silently if they exceed this

## Configuration

Configure task workers in `config/octane.php`:

```php
'swoole' => [
    'options' => [
        'task_worker_num' => 6,           // Number of task workers
        'task_enable_coroutine' => false, // Enable coroutines in task workers
    ],
],
```

Or via CLI:
```bash
php artisan octane:start --server=swoole --task-workers=6
```

## Rules and Gotchas

### Closures Must Be Serializable
Task workers receive closures via serialization. Avoid binding `$this` from a class that holds non-serializable state:
```php
// BAD — captures $this which may not serialize
Octane::concurrently([
    fn() => $this->processOrder(),
]);

// GOOD — use static closures or inject only serializable data
$orderId = $this->order->id;
Octane::concurrently([
    fn() => Order::find($orderId)->process(),
]);
```

### No Shared Memory Between Closures
Each task runs in isolation. Use `Octane::table()` for shared memory if needed (see `swoole-tables.md`).

### Error Handling
Exceptions in concurrent closures are swallowed by default. Wrap closures in try/catch if you need to handle errors:
```php
[$result] = Octane::concurrently([
    function () {
        try {
            return User::count();
        } catch (\Throwable $e) {
            return null;
        }
    },
]);
```

### Not Available Without Swoole
Always guard concurrent code if your app might run on multiple drivers:
```php
if (config('octane.server') === 'swoole') {
    [$a, $b] = Octane::concurrently([...]);
} else {
    $a = firstQuery();
    $b = secondQuery();
}
```

## Tick/Interval Handlers (Swoole only)

Octane also supports recurring background work via ticks:
```php
// In a service provider
Octane::tick('heartbeat', fn () => Cache::set('heartbeat', now()))
    ->seconds(15);

Octane::interval('cleanup', fn () => TemporaryFile::deleteOlderThan(1))
    ->minutes(60);
```

- **Ticks** run on a fixed interval in the main server process
- **Intervals** wrap ticks in a convenient fluent API
- These are not available on RoadRunner or FrankenPHP

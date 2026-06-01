---
name: octane-development
description: "Use this skill when working with Laravel Octane, a long-running PHP worker server (Swoole, FrankenPHP, RoadRunner) where the app boots once and serves many requests instead of rebooting per request like PHP-FPM. Trigger when: installing Octane or starting its server; configuring or detecting the active driver to guard driver-specific code; using Octane::concurrently(), Octane::table(), the octane cache, or shared in-memory state across workers; controlling worker memory growth; or testing with Octane::fake(). Skip for plain PHP-FPM apps with no persistent worker."
license: MIT
metadata:
  author: laravel
---

# Laravel Octane

Octane boots the application container once and reuses it across every request in a long-running worker. Follow these rules to avoid the Octane-specific mistakes that traditional PHP-FPM assumptions cause.

Always verify against the docs. Octane's APIs, config keys, and driver options change between versions. Before giving setup steps, config, or exact API syntax, call the Laravel Boost `search-docs` tool scoped to the installed `laravel/octane` version. Treat the rules below as the durable patterns and treat `search-docs` as the source of truth for specifics like install commands, Caddyfile and `.rr.yaml` config, server flags, and method signatures.

## Detect the Driver First

Read `config('octane.server')` to determine the driver: `swoole`, `roadrunner`, or `frankenphp`. Guard `Octane::concurrently()`, `Octane::table()`, the `octane` cache store, and ticks/intervals behind this check, since they run on Swoole only.

## State Isolation (all drivers)

Avoid storing request-specific state in singletons or static properties. State set during one request survives into the next, which is the most common source of Octane bugs.

```php
// Avoid: holds request 1's user for every later request
$this->app->singleton(UserContext::class);

// Prefer: flushed and re-resolved per request
$this->app->scoped(UserContext::class);
```

- Scope, lazily resolve, or add to the `flush` list (`config/octane.php`) any service that holds data derived from `$request`, `Auth::user()`, or another per-request value.
- Inject `Request` through method parameters or the `request()` helper inside methods. Do not capture it at construction, where it goes stale.
- Reset any static state you must keep on the `RequestTerminated` event, or list the service in `flush`. Octane already flushes framework core (auth, session, cookies). Your application code is your responsibility.

## Memory (all drivers)

Free what you allocate. Workers are long-lived, so anything left referenced accumulates until the worker crashes.

- Set a max-requests or max-jobs recycle limit as the last line of defense against any leak.
- Register event listeners in service providers, never inside request handlers, since the dispatcher keeps every one.
- Clear static arrays in a lifecycle listener or `flush` the service. Never append to them in request handlers.
- Watch for singletons that capture large objects (Eloquent models) in closures, since they are held forever.

## Concurrency (Swoole only)

Guard the driver, then run closures in parallel:

```php
[$users, $orders] = Octane::concurrently([
    fn () => User::all(),
    fn () => Order::pending()->get(),
]);
```

- Pass scalar IDs and re-fetch inside the closure. Do not capture `$this` or non-serializable state, since closures are serialized to task workers.
- Expect results in input order. Wrap closures in try/catch when you need the error, since the default timeout is about 1 second and exceptions are swallowed.

## Swoole Tables and `octane` Cache (Swoole only)

- Pre-size tables at boot (`'name:maxRows'`). You cannot resize them, and exceeding the max fails silently. Treat them as volatile, since data is lost on worker restart, not as a Redis or database replacement.
- Use `incr()` and `decr()` for atomic counters, since there are no transactions.
- Use the `octane` cache store for ephemeral high-frequency data only. It is the same shared memory and is not durable caching.

## Testing (all drivers)

- Call `Octane::fake()` to exercise `concurrently()`, tables, and ticks without a real server. Test correctness rather than parallelism, since closures run sequentially.
- Call `$this->refreshApplication()` between assertions to simulate the per-request scoped-binding flush and prove state does not bleed.
- Guard Swoole-specific tests with `extension_loaded('swoole')` and skip them when the extension is absent.

## Verification

1. Confirm `config('octane.server')` matches the driver assumed by any Swoole-only code.
2. Confirm no request-derived state lives in a `singleton` or static without `scoped`, `flush`, or a reset.
3. Confirm a worker recycle limit (`max_requests` or `max_jobs`) is configured.

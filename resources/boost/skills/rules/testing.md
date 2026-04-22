# Testing Laravel Octane Applications

> **Applies to:** All drivers (Swoole, RoadRunner, FrankenPHP)

## Overview

Testing Octane applications requires simulating the long-running process environment — specifically that the application container persists across requests. Laravel provides `Octane::fake()` for this purpose.

## Octane::fake()

`Octane::fake()` stubs the Octane server so you can test Octane-specific features (concurrent tasks, tables, ticks) without a real server process.

```php
use Laravel\Octane\Facades\Octane;

class OrderServiceTest extends TestCase
{
    public function test_concurrent_queries_return_correct_results(): void
    {
        Octane::fake();

        // Now you can call Octane::concurrently() in tests
        // It will run closures sequentially in test mode
        [$users, $orders] = Octane::concurrently([
            fn () => User::count(),
            fn () => Order::count(),
        ]);

        $this->assertIsInt($users);
        $this->assertIsInt($orders);
    }
}
```

## Testing State Isolation

To verify your code doesn't bleed state between requests, simulate multiple request cycles:

```php
use Laravel\Octane\Facades\Octane;

class UserContextTest extends TestCase
{
    public function test_user_context_does_not_bleed_between_requests(): void
    {
        Octane::fake();

        // Simulate first request
        $this->actingAs(User::factory()->create(['name' => 'Alice']));
        $response1 = $this->get('/profile');
        $response1->assertSee('Alice');

        // Flush per-request state as Octane does
        $this->refreshApplication();

        // Simulate second request — should not see Alice's context
        $response2 = $this->get('/profile');
        $response2->assertStatus(302); // Redirected to login
    }
}
```

## Testing Swoole Tables

```php
use Laravel\Octane\Facades\Octane;

class RateLimiterTest extends TestCase
{
    public function test_rate_limiter_uses_swoole_table(): void
    {
        Octane::fake([
            'tables' => [
                'rate_limits:100' => [
                    'hits' => \Swoole\Table::TYPE_INT,
                ],
            ],
        ]);

        $table = Octane::table('rate_limits');
        $table->set('user_1', ['hits' => 0]);
        $table->incr('user_1', 'hits');

        $this->assertEquals(1, $table->get('user_1', 'hits'));
    }
}
```

## Testing Tick/Interval Handlers

Ticks cannot run in test mode (no server loop). Instead, test the closure directly:

```php
class HeartbeatTickTest extends TestCase
{
    public function test_heartbeat_updates_cache(): void
    {
        // Don't rely on the tick being called — call the closure directly
        Cache::forget('heartbeat');

        $heartbeatHandler = fn () => Cache::set('heartbeat', now());
        $heartbeatHandler();

        $this->assertNotNull(Cache::get('heartbeat'));
    }
}
```

## Testing Concurrent Task Failures

```php
use Laravel\Octane\Facades\Octane;

class ConcurrentTaskTest extends TestCase
{
    public function test_handles_partial_task_failure_gracefully(): void
    {
        Octane::fake();

        [$result] = Octane::concurrently([
            function () {
                try {
                    throw new \RuntimeException('Task failed');
                } catch (\Throwable $e) {
                    return null;
                }
            },
        ]);

        $this->assertNull($result);
    }
}
```

## Using refreshApplication() in Tests

In feature tests, `$this->refreshApplication()` re-bootstraps the application, simulating the scoped binding flush that Octane does between requests:

```php
public function test_scoped_service_is_fresh_per_request(): void
{
    Octane::fake();

    $service1 = app(RequestScopedService::class);
    $service1->setValue('hello');

    $this->refreshApplication(); // Simulate new request

    $service2 = app(RequestScopedService::class);
    $this->assertNull($service2->getValue()); // Should be null — fresh instance
}
```

## Integration Testing with a Real Octane Process

For end-to-end integration tests against a real Octane server:

```bash
# Start Octane in the background before running Dusk tests
php artisan octane:start --server=frankenphp --port=8000 &
php artisan dusk --env=testing
```

In your `DuskTestCase`:
```php
protected function driver(): RemoteWebDriver
{
    // Point to Octane server
    return RemoteWebDriver::create(
        $this->driverUrl(),
        DesiredCapabilities::chrome()
    );
}

public static function setUpBeforeClass(): void
{
    // Ensure Octane is running before tests start
    static::startChromeDriver(['port' => 9515]);
}
```

## Common Test Gotchas

**Issue: Singleton state from a previous test bleeds into the next**
*Fix:* Call `$this->refreshApplication()` between tests, or use scoped bindings.

**Issue: `Octane::concurrently()` runs sequentially in tests**
*This is expected.* `Octane::fake()` runs concurrent closures one by one. Test correctness, not performance.

**Issue: Swoole extension not installed in CI**
*Fix:* Conditionally skip Swoole-specific tests:
```php
$this->skipUnless(extension_loaded('swoole'), 'Swoole extension not available');
```

Or use mocks instead of actual Swoole tables in CI.

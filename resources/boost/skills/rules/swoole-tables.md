# Swoole Tables & Octane Cache

> **Applies to:** Swoole and OpenSwoole drivers only

## Swoole Tables (Shared Memory)

Swoole tables provide ultra-fast, shared-memory data storage accessible across all worker processes simultaneously. Unlike Redis or the database, they reside in native shared memory with no serialization overhead.

### Defining Tables

Define tables in `config/octane.php`:

```php
'tables' => [
    'users:1000' => [         // Table name : max rows
        'name'    => Table::TYPE_STRING,     // 1024 bytes default
        'balance' => Table::TYPE_FLOAT,
        'votes'   => Table::TYPE_INT,
    ],

    'rate_limits:5000' => [
        'hits'       => Table::TYPE_INT,
        'expires_at' => Table::TYPE_INT,
    ],
],
```

> **Tip:** The format is `'name:maxRows'`. Max rows is pre-allocated at boot time — choose wisely, as you cannot resize after boot.

### Accessing Tables

```php
use Laravel\Octane\Facades\Octane;

// Get the table instance
$table = Octane::table('users');

// Set a row
$table->set('user_1', ['name' => 'Alice', 'balance' => 100.0, 'votes' => 5]);

// Get a row
$row = $table->get('user_1');                // returns array or false
$name = $table->get('user_1', 'name');       // returns specific column value

// Check existence
$exists = $table->exists('user_1');

// Delete a row
$table->del('user_1');

// Increment/decrement
$table->incr('user_1', 'votes');       // +1
$table->decr('user_1', 'votes', 2);    // -2

// Iterate
foreach ($table as $key => $row) {
    // ...
}
```

### Rules and Limits

- **String column size**: Default is 1024 bytes. Override with `[Table::TYPE_STRING, 256]`.
- **Max rows is hard**: Inserting beyond the max will fail silently or corrupt. Pre-size generously.
- **No transactions**: Swoole tables are not atomic. For counters, use `incr()`/`decr()` which are atomic.
- **Data is lost on worker restart**: Tables are in-process shared memory, not persisted.
- **Not available on other drivers**: Always guard with `config('octane.server') === 'swoole'`.

### String Column with Custom Size

```php
'users:1000' => [
    'bio' => [Table::TYPE_STRING, 4096],  // 4KB for bio field
],
```

## Octane Cache (Swoole-backed)

The Octane cache driver wraps a Swoole table for a standard Laravel cache API. It's useful for ultra-low-latency cache operations that don't need to survive a restart.

### Configuration

Add to `config/cache.php`:

```php
'stores' => [
    'octane' => [
        'driver' => 'octane',
    ],
],
```

### Usage

```php
use Illuminate\Support\Facades\Cache;

Cache::store('octane')->put('key', 'value', 30);
$value = Cache::store('octane')->get('key');
Cache::store('octane')->forget('key');
```

### Octane Cache Limits

- Maximum **entries**: 1000 (default, defined by the internal Swoole table size)
- Maximum **bytes per entry**: 10000 (default)
- No TTL eviction — Octane cache respects the expiry you set, but eviction is checked at read time, not proactively
- Data is lost on server restart or worker recycle

> For production caching, prefer Redis or Memcached. Use Octane cache only for ephemeral, high-frequency, low-value data (rate counters, feature flags, etc.).

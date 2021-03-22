<?php

namespace Laravel\Octane\Cache;

use Closure;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Carbon;
use Swoole\Table;

class OctaneStore implements Store
{
    /**
     * All of the registered interval caches.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $intervals;

    public function __construct(protected Table $table)
    {
        $this->intervals = collect();
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string|array  $key
     * @return mixed
     */
    public function get($key)
    {
        $record = $this->table[$key] ?? null;

        if (is_null($record) || $record['expiration'] <= Carbon::now()->getTimestamp()) {
            return null;
        }

        return unserialize($record['value']);
    }

    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @param  array  $keys
     * @return array
     */
    public function many(array $keys)
    {
        return collect($keys)->mapWithKeys(fn ($key) => [$key => $this->get($key)])->all();
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     * @return bool
     */
    public function put($key, $value, $seconds)
    {
        $this->table[$key] = [
            'value' => serialize($value),
            'expiration' => Carbon::now()->getTimestamp() + $seconds
        ];

        return true;
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  array  $values
     * @param  int  $seconds
     * @return bool
     */
    public function putMany(array $values, $seconds)
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }

        return true;
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        $record = $this->table[$key];

        if (is_null($record) || $record['expiration'] <= Carbon::now()->getTimestamp()) {
            return tap($value, fn ($value) => $this->put($key, $value, 31536000));
        }

        return tap((int) (unserialize($record['value']) + $value), function ($value) use ($key, $record) {
            $this->put($key, $value, $record['expiration'] - Carbon::now()->getTimestamp());
        });
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        return $this->increment($key, $value * -1);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, 31536000);
    }

    /**
     * Register a cache key that should be refreshed at a given interval (in minutes).
     *
     * @param  string  $key
     * @param  \Closure  $resolver
     * @param  int  $refreshSeconds
     * @return mixed
     */
    public function interval($key, Closure $resolver, $refreshSeconds)
    {
        $value = $resolver();

        $this->forever($key, $value);

        $this->intervals[$key] = [
            'resolver' => $resolver,
            'lastRefreshedAt' => Carbon::now()->getTimestamp(),
            'refreshInterval' => $refreshSeconds,
        ];

        return $value;
    }

    /**
     * Refresh all of the applicable interval caches.
     *
     * @return void
     */
    public function refreshIntervalCaches()
    {
        foreach ($this->intervals as $key => &$interval) {
            $this->forever($key, call_user_func($interval['resolver']));
        }
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        unset($this->table[$key]);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        foreach ($this->table as $key => $record) {
            $this->forget($key);
        }

        return true;
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return '';
    }
}

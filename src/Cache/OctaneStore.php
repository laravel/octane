<?php

namespace Laravel\Octane\Cache;

use Closure;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Carbon;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;

class OctaneStore implements Store
{
    protected const ONE_YEAR = 31536000;

    /**
     * All of the registered interval caches.
     *
     * @var array
     */
    protected $intervals = [];

    /**
     * Create a new Octane store.
     *
     * @param  \Swoole\Table  $table
     * @return void
     */
    public function __construct(protected $table)
    {
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string|array  $key
     * @return mixed
     */
    public function get($key)
    {
        $record = $this->table->get($key);

        if (! $this->recordIsFalseOrExpired($record)) {
            return unserialize($record['value']);
        }

        if (in_array($key, $this->intervals) &&
            ! is_null($interval = $this->getInterval($key))) {
            return $interval['resolver']();
        }
    }

    /**
     * Retrieve an interval item from the cache.
     *
     * @param  string  $key
     * @return array|null
     */
    protected function getInterval($key)
    {
        $interval = $this->get('interval-'.$key);

        return $interval ? unserialize($interval) : null;
    }

    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
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
        return $this->table->set($key, [
            'value' => serialize($value),
            'expiration' => Carbon::now()->getTimestamp() + $seconds,
        ]);
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
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
        $record = $this->table->get($key);

        if ($this->recordIsFalseOrExpired($record)) {
            return tap($value, fn ($value) => $this->put($key, $value, static::ONE_YEAR));
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
        return $this->put($key, $value, static::ONE_YEAR);
    }

    /**
     * Register a cache key that should be refreshed at a given interval (in minutes).
     *
     * @param  string  $key
     * @param  int  $seconds
     * @return void
     */
    public function interval($key, Closure $resolver, $seconds)
    {
        if (! is_null($this->getInterval($key))) {
            $this->intervals[] = $key;

            return;
        }

        $this->forever('interval-'.$key, serialize([
            'resolver' => new SerializableClosure($resolver),
            'lastRefreshedAt' => null,
            'refreshInterval' => $seconds,
        ]));

        $this->intervals[] = $key;
    }

    /**
     * Refresh all of the applicable interval caches.
     *
     * @return void
     */
    public function refreshIntervalCaches()
    {
        foreach ($this->intervals as $key) {
            if (! $this->intervalShouldBeRefreshed($interval = $this->getInterval($key))) {
                continue;
            }

            try {
                $this->forever('interval-'.$key, serialize(array_merge(
                    $interval, ['lastRefreshedAt' => Carbon::now()->getTimestamp()],
                )));

                $this->forever($key, $interval['resolver']());
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Determine if the given interval record should be refreshed.
     *
     * @return bool
     */
    protected function intervalShouldBeRefreshed(array $interval)
    {
        return is_null($interval['lastRefreshedAt']) ||
               (Carbon::now()->getTimestamp() - $interval['lastRefreshedAt']) >= $interval['refreshInterval'];
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        return $this->table->del($key);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        foreach ($this->table as $key => $record) {
            if (str_starts_with($key, 'interval-')) {
                continue;
            }

            $this->forget($key);
        }

        return true;
    }

    /**
     * Determine if the record is missing or expired.
     *
     * @param  array|null  $record
     * @return bool
     */
    protected function recordIsFalseOrExpired($record)
    {
        return $record === false || $record['expiration'] <= Carbon::now()->getTimestamp();
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

<?php

namespace Laravel\Octane\Swoole;

use Illuminate\Cache\ArrayLock;
use Illuminate\Cache\Lock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Swoole\Table;

class SwoolTableLock extends ArrayLock
{
    protected $store;

    /**
     * Create a new lock instance.
     *
     * @param  \Illuminate\Cache\ArrayStore  $store
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return void
     */
    public function __construct($store, $name, $seconds, $owner = null)
    {
        parent::__construct($name, $seconds, $owner);

        $this->store = $store;
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire()
    {
        $aquired = $this->store->get($this->name);

        $expiration = $aquired['expiresAt'] ?? Carbon::now()->addSecond();

        if ($this->exists() && $expiration->isFuture()) {
            return false;
        }

        $this->store->set($this->name,
            [
                'owner' => $this->owner,
                'expiresAt' => $this->seconds === 0 ? null : Carbon::now()->addSeconds($this->seconds),
            ]
        );

        return true;
    }

    /**
     * Determine if the current lock exists.
     *
     * @return bool
     */
    protected function exists()
    {
        return $this->store->exists($this->name);
    }

    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return string
     */
    protected function getCurrentOwner()
    {
        if (! $this->exists()) {
            return null;
        }

        return $this->store->get($this->name)['owner'];
    }

    /**
     * Releases this lock in disregard of ownership.
     *
     * @return void
     */
    public function forceRelease()
    {
        $this->store->del($this->name);
    }
}

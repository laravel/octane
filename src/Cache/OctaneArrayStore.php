<?php

namespace Laravel\Octane\Cache;

use Closure;
use Illuminate\Cache\ArrayStore;

class OctaneArrayStore extends ArrayStore
{
    /**
     * Register a cache key that should be refreshed at a given interval (in minutes).
     *
     * @param  string  $key
     * @param  \Closure  $resolver
     * @param  int  $seconds
     * @return mixed
     */
    public function interval($key, Closure $resolver, $seconds)
    {
        return $resolver();
    }

    /**
     * Refresh all of the applicable interval caches.
     *
     * @return void
     */
    public function refreshIntervalCaches()
    {
        //
    }
}

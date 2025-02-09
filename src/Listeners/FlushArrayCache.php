<?php

namespace Laravel\Octane\Listeners;

class FlushArrayCache
{

    /**
     * @var ?\Illuminate\Cache\ArrayStore
     */
    private $arrayCache = null;

    public function __construct()
    {
        if (app()->make('config')->get('cache.stores.array')) {
            $this->arrayCache = app()->make('cache')->store('array');
        }
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if ($this->arrayCache) {
            $this->arrayCache->flush();
        }
    }
}

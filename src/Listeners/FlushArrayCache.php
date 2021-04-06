<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Support\Facades\Cache;

class FlushArrayCache
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        if (config('cache.stores.array')) {
            $event->sandbox->make('cache')->store('array')->flush();
        }
    }
}

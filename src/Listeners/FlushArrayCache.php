<?php

namespace Laravel\Octane\Listeners;

class FlushArrayCache
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (config('cache.stores.array')) {
            $event->sandbox->make('cache')->store('array')->flush();
        }
    }
}

<?php

namespace Laravel\Octane\Listeners;

class ReleaseFreedMemory
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $garbage = (int) $event->app->make('config')->get('octane.garbage');

        // PHP keeps freed memory reserved for the worker instead of returning it to the system,
        // so a single large request would otherwise hold on to its peak until the worker exits.
        if ($garbage && (memory_get_usage(true) / 1024 / 1024) > $garbage) {
            gc_mem_caches();
        }
    }
}

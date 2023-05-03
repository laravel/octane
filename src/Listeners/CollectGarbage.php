<?php

namespace Laravel\Octane\Listeners;

class CollectGarbage
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $garbage = (int) $event->app->make('config')->get('octane.garbage');

        if ($garbage && (memory_get_usage() / 1024 / 1024) > $garbage) {
            gc_collect_cycles();
        }
    }
}

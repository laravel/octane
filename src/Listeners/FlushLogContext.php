<?php

namespace Laravel\Octane\Listeners;

class FlushLogContext
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('log')) {
            return;
        }

        if (method_exists($event->sandbox['log'], 'flushSharedContext')) {
            $event->sandbox['log']->flushSharedContext();
        }

        if (method_exists($event->sandbox['log']->driver(), 'withoutContext')) {
            $event->sandbox['log']->withoutContext();
        }
    }
}

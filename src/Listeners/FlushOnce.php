<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Support\Once;

class FlushOnce
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (class_exists(Once::class)) {
            Once::flush();
        }
    }
}

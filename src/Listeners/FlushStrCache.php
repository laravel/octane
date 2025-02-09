<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Support\Str;

class FlushStrCache
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        Str::flushCache();
    }
}

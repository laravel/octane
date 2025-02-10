<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Facade;

class PrepareViteForNextOperation
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        Facade::clearResolvedInstance(Vite::class);
    }
}

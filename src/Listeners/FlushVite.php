<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Foundation\Vite;

class FlushVite
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        if (! $event->sandbox->resolved(Vite::class)) {
            return;
        }

        $vite = $event->sandbox->make(Vite::class);

        if (method_exists($vite, 'flush')) {
            $vite->flush();
        }
    }
}

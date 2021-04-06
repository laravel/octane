<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Contracts\Auth\Access\Gate;

class GiveNewApplicationInstanceToAuthorizationGate
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        $event->sandbox->make(Gate::class)->setContainer($event->sandbox);
    }
}

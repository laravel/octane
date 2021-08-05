<?php

namespace Laravel\Octane\Listeners;

class FlushAuthenticationState
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('auth') || ! $event->sandbox->resolved('auth.driver')) {
            return;
        }

        $event->sandbox->forgetInstance('auth.driver');

        with($event->sandbox->make('auth'), function ($auth) use ($event) {
            $auth->setApplication($event->sandbox);
            $auth->forgetGuards();
        });
    }
}

<?php

namespace Laravel\Octane\Listeners;

class EnforceRequestScheme
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->make('config')->get('octane.https')) {
            return;
        }

        $event->sandbox->make('url')->forceScheme('https');

        $event->request->server->set('HTTPS', 'on');
    }
}

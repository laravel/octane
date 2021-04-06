<?php

namespace Laravel\Octane\Listeners;

class EnsureRequestServerPortMatchesScheme
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        $port = $event->request->getPort();

        if (is_null($port) || $port === '') {
            $event->request->server->set(
                'SERVER_PORT',
                $event->request->getScheme() === 'https' ? 443 : 80
            );
        }
    }
}

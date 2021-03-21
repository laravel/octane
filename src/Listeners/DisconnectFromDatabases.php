<?php

namespace Laravel\Octane\Listeners;

class DisconnectFromDatabases
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        foreach ($event->sandbox->make('db')->getConnections() as $connection) {
            $connection->disconnect();
        }
    }
}

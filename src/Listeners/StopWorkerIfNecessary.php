<?php

namespace Laravel\Octane\Listeners;

use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Contracts\StoppableClient;

class StopWorkerIfNecessary
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $client = $event->sandbox->make(Client::class);

        if ($client instanceof StoppableClient) {
            $client->stop();
        }
    }
}

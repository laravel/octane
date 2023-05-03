<?php

namespace Laravel\Octane\Listeners;

use Monolog\ResettableInterface;

class FlushMonologState
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('log')) {
            return;
        }

        collect($event->sandbox->make('log')->getChannels())
            ->map->getLogger()
            ->filter(function ($logger) {
                return $logger instanceof ResettableInterface;
            })->each->reset();
    }
}

<?php

namespace Laravel\Octane\Listeners;

use Laravel\Scout\EngineManager;

class PrepareScoutForNextOperation
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved(EngineManager::class)) {
            return;
        }

        $factory = $event->sandbox->make(EngineManager::class);

        if (! method_exists($factory, 'forgetEngines')) {
            return;
        }

        $factory->forgetEngines();
    }
}

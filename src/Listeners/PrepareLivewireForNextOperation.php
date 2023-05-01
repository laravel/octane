<?php

namespace Laravel\Octane\Listeners;

use Livewire\LivewireManager;

class PrepareLivewireForNextOperation
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved(LivewireManager::class)) {
            return;
        }

        $manager = $event->sandbox->make(LivewireManager::class);

        if (method_exists($manager, 'flushState')) {
            $manager->flushState();
        }
    }
}

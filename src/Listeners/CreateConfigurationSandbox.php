<?php

namespace Laravel\Octane\Listeners;

class CreateConfigurationSandbox
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $event->sandbox->instance('config', clone $event->sandbox['config']);
    }
}

<?php

namespace Laravel\Octane\Listeners;

class CreateConfigurationSandbox
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        $event->sandbox->instance('config', clone $event->sandbox['config']);
    }
}

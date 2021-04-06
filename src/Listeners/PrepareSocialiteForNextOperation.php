<?php

namespace Laravel\Octane\Listeners;

use Laravel\Socialite\Contracts\Factory;

class PrepareSocialiteForNextOperation
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved(Factory::class)) {
            return;
        }

        $factory = $event->sandbox->make(Factory::class);

        if (! method_exists($factory, 'forgetDrivers')) {
            return;
        }

        $factory->forgetDrivers();
        $factory->setContainer($event->sandbox);
    }
}

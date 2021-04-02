<?php

namespace Laravel\Octane\Listeners;

use Laravel\Socialite\Contracts\Factory;

class GiveNewApplicationInstanceToSocialiteManager
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        if ($event->sandbox->resolved(Factory::class)) {
            $factory = $event->sandbox->make(Factory::class);

            if (! method_exists($factory, 'forgetDrivers')) {
                return;
            }

            $factory->forgetDrivers();
            $factory->setContainer($event->sandbox);
        }
    }
}

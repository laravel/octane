<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToMailManager
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('mail.manager')) {
            return;
        }

        with($event->sandbox->make('mail.manager'), function ($manager) use ($event) {
            $manager->setApplication($event->sandbox);
            $manager->forgetMailers();
        });
    }
}

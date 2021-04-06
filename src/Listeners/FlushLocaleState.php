<?php

namespace Laravel\Octane\Listeners;

class FlushLocaleState
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        $config = $event->sandbox->make('config');

        tap($event->sandbox->make('translator'), function ($translator) use ($config) {
            $translator->setLocale($config->get('app.locale'));
            $translator->setFallback($config->get('app.fallback_locale'));
        });
    }
}

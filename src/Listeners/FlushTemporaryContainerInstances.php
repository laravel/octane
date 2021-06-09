<?php

namespace Laravel\Octane\Listeners;

class FlushTemporaryContainerInstances
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        $event->sandbox->flush();

        if (method_exists($event->app, 'resetScope')) {
            $event->app->resetScope();
        }

        foreach ($event->sandbox->make('config')->get('octane.flush', []) as $binding) {
            $event->app->forgetInstance($binding);
        }
    }
}

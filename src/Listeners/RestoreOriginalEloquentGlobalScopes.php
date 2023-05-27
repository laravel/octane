<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Database\Eloquent\Model;

class RestoreOriginalEloquentGlobalScopes
{
    /**
     * Handle the event.
     *
     * @param  \Laravel\Octane\Events\RequestTerminated  $event
     * @return void
     */
    public function handle($event)
    {
        if ($event->app->bound('eloquent.scopes')) {
            Model::setAllGlobalScopes($event->app->make('eloquent.scopes'));
        }
    }
}

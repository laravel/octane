<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Database\Eloquent\Model;

class StoreOriginalEloquentGlobalScopes
{
    /**
     * Handle the event.
     *
     * @param  \Laravel\Octane\Events\RequestReceived  $event
     * @return void
     */
    public function handle($event)
    {
        $event->app->instance('eloquent.scopes', Model::getAllGlobalScopes());
    }
}

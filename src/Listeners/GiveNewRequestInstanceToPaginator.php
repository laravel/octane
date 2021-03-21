<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Pagination\PaginationState;

class GiveNewRequestInstanceToPaginator
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        // PaginationState::resolveUsing($event->sandbox);
    }
}

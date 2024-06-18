<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Support\Facades\DB;

class RollbackActiveTransactions
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (class_exists(DB::class) && DB::getPdo()->inTransaction()) {
            DB::getPdo()->rollBack();
        }
    }
}

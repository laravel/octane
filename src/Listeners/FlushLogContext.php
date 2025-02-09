<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Log\LogManager;

class FlushLogContext
{

    /**
     * @var ?LogManager
     */
    private $log = null;

    public function __construct()
    {
        if (app()->resolved('log')) {
            $this->log = app('log');
        }
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $this->log) {
            return;
        }

        if (method_exists($this->log, 'flushSharedContext')) {
            $this->log->flushSharedContext();
        }

        if (method_exists($this->log->driver(), 'withoutContext')) {
            $this->log->driver()->withoutContext();
        }

        if (method_exists($this->log, 'withoutContext')) {
            $this->log->withoutContext();
        }
    }
}

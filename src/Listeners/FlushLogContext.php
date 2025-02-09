<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Log\LogManager;

class FlushLogContext
{
    /**
     * @var ?LogManager
     */
    private $log = null;
    private $logDefaultDriver = null;

    public function __construct()
    {
        if (app()->resolved('log')) {
            $this->log = app('log');
            $this->logDefaultDriver = $this->log->driver();
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

        if (method_exists($this->logDefaultDriver, 'withoutContext')) {
            $this->logDefaultDriver->withoutContext();
        }

        if (method_exists($this->log, 'withoutContext')) {
            $this->log->withoutContext();
        }
    }
}

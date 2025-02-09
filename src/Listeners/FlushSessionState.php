<?php

namespace Laravel\Octane\Listeners;

class FlushSessionState
{
    /**
     * @var ?\Illuminate\Session\SessionManager
     */
    private $session = null;

    public function __construct()
    {
        if (app()->resolved('session')) {
            $this->session = app('session');
        }
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if ($this->session === null) {
            return;
        }

        $driver = $this->session->driver();
        $driver->flush();
        $driver->regenerate();
    }
}

<?php

namespace Laravel\Octane\Listeners;

class FlushAuthenticationState
{
    /**
     * @var ?\Illuminate\Auth\AuthManager
     */
    private $auth = null;

    public function __construct()
    {
        if (app()->resolved('auth.driver')) {
            app()->forgetInstance('auth.driver');
        }

        if (app()->resolved('auth')) {
            $this->auth = app()->make('auth');
        }
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if ($this->auth) {
            $this->auth->forgetGuards();
        }
    }
}

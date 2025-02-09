<?php

namespace Laravel\Octane\Listeners;

class FlushQueuedCookies
{
    /**
     * @var ?\Illuminate\Cookie\CookieJar
     */
    private $cookie = null;

    public function __construct()
    {
        if (app()->resolved('cookie')) {
            $this->cookie = app('cookie');
        }
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if ($this->cookie === null) {
            return;
        }

        $this->cookie->flushQueuedCookies();
    }
}

<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Routing\UrlGenerator;

class EnforceRequestScheme
{
    /**
     * @var UrlGenerator
     */
    private $url;

    private bool $octaneHttps;

    public function __construct()
    {
        $this->octaneHttps = (bool) config('octane.https');
        $this->url = app('url');
    }

    public function handle($event): void
    {
        if (! $this->octaneHttps) {
            return;
        }

        $this->url->forceScheme('https');

        $event->request->server->set('HTTPS', 'on');
    }
}

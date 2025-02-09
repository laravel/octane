<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Routing\UrlGenerator;

class CreateUrlGeneratorSandbox
{

    /**
     * @var UrlGenerator
     */
    private $url;

    public function __construct()
    {
        $this->url = app('url');
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $event->sandbox->instance('url', clone $this->url);
    }
}

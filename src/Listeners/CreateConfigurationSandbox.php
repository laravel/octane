<?php

namespace Laravel\Octane\Listeners;

class CreateConfigurationSandbox
{

    /**
     * @var \Illuminate\Config\Repository
     */
    private $config;

    public function __construct()
    {
        $this->config = app('config');
    }

    public function handle($event): void
    {
        $event->sandbox->instance('config', clone $this->config);
    }
}

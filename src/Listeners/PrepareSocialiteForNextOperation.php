<?php

namespace Laravel\Octane\Listeners;

use Laravel\Socialite\Contracts\Factory;

class PrepareSocialiteForNextOperation
{
    /**
     * @var ?Factory
     */
    private $factory = null;

    public function __construct()
    {
        if (app()->resolved(Factory::class)) {
            $this->factory = app(Factory::class);
        }
    }

    public function handle($event): void
    {
        if ($this->factory === null) {
            return;
        }

        if (! method_exists($this->factory, 'forgetDrivers')) {
            return;
        }

        $this->factory->forgetDrivers();
    }
}

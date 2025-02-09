<?php

namespace Laravel\Octane\Tests\Listeners;

class TestEventListener
{
    public function handle($event): void
    {
        $event->app['hasPassedTest'] = true;
    }
}

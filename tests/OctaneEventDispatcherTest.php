<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Laravel\Octane\OctaneEventDispatcher;
use Laravel\Octane\Tests\Listeners\TestEvent;
use Laravel\Octane\Tests\Listeners\TestEventInterface;
use Laravel\Octane\Tests\Listeners\TestEventListener;

class OctaneEventDispatcherTest extends TestCase
{
    public function test_register_a_specific_event_listeners()
    {
        $app = new Application;
        $dispatcher = new OctaneEventDispatcher;
        $allEventsAndListeners = [
            TestEvent::class => [TestEventListener::class],
        ];

        $dispatcher->registerListener(TestEvent::class, $allEventsAndListeners);

        $dispatcher->dispatchEvent(new TestEvent($app));

        self::assertTrue($app['hasPassedTest']);
    }

    public function test_register_a_listeners_and_dispatch_its_interface()
    {
        $app = new Application;
        $dispatcher = new OctaneEventDispatcher;
        $allEventsAndListeners = [
            TestEventInterface::class => [TestEventListener::class],
            TestEvent::class => [],
        ];

        $dispatcher->registerAllListeners($allEventsAndListeners);

        $dispatcher->dispatchEvent(new TestEvent($app));

        self::assertTrue($app['hasPassedTest']);
    }
}

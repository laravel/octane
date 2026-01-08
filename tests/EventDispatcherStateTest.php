<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class EventDispatcherStateTest extends TestCase
{
    public function test_event_dispatcher_resolves_queue_using_sandbox()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app['events'];
        $app['queue'];

        $app['router']->get('/first', function (Application $app) {
            $queueResolver = (fn () => $this->queueResolver)->call($app['events']);

            return spl_object_hash($queueResolver()->getApplication());
        });

        $worker->run();

        $this->assertNotEquals($client->responses[0]->original, $client->responses[1]->original);
    }
}

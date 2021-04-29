<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class QueuedCookieFlushingTest extends TestCase
{
    public function test_queued_cookies_from_previous_requests_are_flushed(): void
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/first', function (Application $app) {
            $app['cookie']->queue('color', 'blue');
        });

        $app['router']->middleware('web')->get('/second', function (Application $app) {
        });

        $worker->run();

        $this->assertTrue(collect($client->responses[0]->headers->getCookies())->filter(fn ($c) => $c->getName() == 'color')->isNotEmpty());
        $this->assertTrue(collect($client->responses[1]->headers->getCookies())->filter(fn ($c) => $c->getName() == 'color')->isEmpty());
    }
}

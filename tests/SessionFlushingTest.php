<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class SessionFlushingTest extends TestCase
{
    public function test_session_is_flushed_between_requests(): void
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/first', function (Application $app) {
            $app['session']->put('color', 'blue');
        });

        $app['router']->middleware('web')->get('/second', function (Application $app) {
            return $app['session']->get('color', 'green');
        });

        $worker->run();

        $this->assertEquals('green', $client->responses[1]->getContent());
    }
}

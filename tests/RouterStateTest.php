<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;

class RouterStateTest extends TestCase
{
    public function test_router_and_route_container_is_refreshed_across_subsequent_requests(): void
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/first', function (Request $request) {
            return $request->url();
        });

        $app['router']->middleware('web')->get('/second', function (Request $request) {
            return $request->url();
        });

        $worker->run();

        $this->assertEquals('http://localhost/first', $client->responses[0]->getContent());
        $this->assertEquals('http://localhost/second', $client->responses[1]->getContent());
    }
}

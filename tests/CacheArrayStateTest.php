<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class CacheArrayStateTest extends TestCase
{
    public function test_array_cache_is_flushed_between_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/test-cache?remember=first', 'GET'),
            Request::create('/test-cache?remember=second', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/test-cache', function (Application $app, Request $request) {
            return $app['cache']->driver('array')->rememberForever('nitro', fn () => $request->query('remember'));
        });

        $worker->run();

        $this->assertEquals('first', $client->responses[0]->getContent());
        $this->assertEquals('second', $client->responses[1]->getContent());
    }
}

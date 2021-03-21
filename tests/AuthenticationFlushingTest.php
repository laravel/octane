<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class AuthenticationFlushingTest extends TestCase
{
    /** @test */
    public function test_authentication_state_is_flushed_across_subsequent_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['auth']->viaRequest('octane', function (Request $request) {
            return new AuthenticationFlushingTestFakeUser($request->url());
        });

        $app['config']->set('auth.guards.octane', [
            'driver' => 'octane',
            'provider' => 'users',
        ]);

        $app['router']->get('/first', function (Application $app) {
            return $app['auth']->guard('octane')->user()->state;
        });

        $app['router']->get('/second', function (Application $app) {
            return $app['auth']->guard('octane')->user()->state;
        });

        $worker->run();

        $this->assertEquals('http://localhost/first', $client->responses[0]->getContent());
        $this->assertEquals('http://localhost/second', $client->responses[1]->getContent());
    }
}

class AuthenticationFlushingTestFakeUser
{
    public $state;

    public function __construct($state)
    {
        $this->state = $state;
    }
}

<?php

namespace Laravel\Octane\Tests;

use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuthorizationStateTest extends TestCase
{
    /** @test */
    public function test_authorization_state_is_updated_across_subsequent_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app[GateContract::class]->define('update-post', [AuthorizationStateTestPolicy::class, 'update']);

        $app['router']->get('/first', function (Application $app) {
            Gate::check('update-post');

            return $_SERVER['__request.url'];
        });

        $app['router']->get('/second', function (Application $app) {
            Gate::check('update-post');

            return $_SERVER['__request.url'];
        });

        $worker->run();

        $this->assertEquals('http://localhost/first', $client->responses[0]->getContent());
        $this->assertEquals('http://localhost/second', $client->responses[1]->getContent());

        unset($_SERVER['__request.url']);
    }
}

class AuthorizationStateTestPolicy
{
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $_SERVER['__request.url'] = $request->url();
    }

    public function update($user = null)
    {
        return true;
    }
}

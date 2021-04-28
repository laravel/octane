<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class RedirectorStateTest extends TestCase
{
    public function test_redirector_has_correct_request_state_across_subsequent_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->get('/first', function (Application $app) {
            return $app['redirect']->refresh();
        });

        $app['router']->get('/second', function (Application $app) {
            return $app['redirect']->refresh();
        });

        $worker->run();

        $this->assertEquals('http://localhost/first', $client->responses[0]->getTargetUrl());
        $this->assertEquals('http://localhost/second', $client->responses[1]->getTargetUrl());
    }
}

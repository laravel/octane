<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class ConfigurationSandboxTest extends TestCase
{
    public function test_config_is_reset_between_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['config']->set('octane.test', 'original');

        $app['router']->get('/first', function (Application $app) {
            $app['config']->set('octane.test', 'changed');
        });

        $app['router']->get('/second', function (Application $app) {
            return $app['config']->get('octane.test');
        });

        $worker->run();

        $this->assertEquals('original', $client->responses[1]->getContent());
    }
}

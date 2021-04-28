<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class ValidationFactoryStateTest extends TestCase
{
    public function test_validation_factory_has_fresh_application_instance()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app['validator'];

        $app['router']->get('/first', function (Application $app) {
            return spl_object_hash($app['validator']->getContainer());
        });

        $worker->run();

        $this->assertNotEquals($client->responses[0]->original, $client->responses[1]->original);
    }
}

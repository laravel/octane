<?php

namespace Laravel\Octane\Tests;

use Illuminate\Contracts\Pipeline\Hub;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class PipelineHubStateTest extends TestCase
{
    /** @test */
    public function test_pipeline_hub_has_fresh_application_instance()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app[Hub::class];

        $app['router']->get('/first', function (Application $app) {
            return spl_object_hash($app[Hub::class]->getContainer());
        });

        $worker->run();

        $this->assertNotEquals($client->responses[0]->original, $client->responses[1]->original);
    }
}

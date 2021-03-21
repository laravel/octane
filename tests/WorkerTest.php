<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;

class WorkerTest extends TestCase
{
    /** @test */
    public function test_worker_can_dispatch_request_to_application_and_returns_responses_to_client()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->get('/first', fn () => 'First Response');
        $app['router']->get('/second', fn () => 'Second Response');

        $worker->run();

        $this->assertCount(2, $client->responses);
        $this->assertEquals('First Response', $client->responses[0]->getContent());
        $this->assertEquals('Second Response', $client->responses[1]->getContent());
    }
}

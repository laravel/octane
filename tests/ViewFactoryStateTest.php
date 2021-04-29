<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class ViewFactoryStateTest extends TestCase
{
    public function test_view_factory_application_is_updated_on_subsequent_requests(): void
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app['router']->get('/first', function (Application $app) {
            return spl_object_hash($app['view']->getShared()['app']);
        });

        $worker->run();

        $this->assertNotEquals(
            $client->responses[0]->getContent(),
            $client->responses[1]->getContent()
        );
    }

    public function test_shared_view_state_is_persisted_across_subsequent_requests(): void
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->get('/first', function (Application $app) {
            $app['view']->share('foo', 'bar');
        });

        $app['router']->get('/second', function (Application $app) {
            return $app['view']->getShared()['foo'] ?? 'missing';
        });

        $worker->run();

        $this->assertEquals('bar', $client->responses[1]->original);
    }
}

<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OctaneRouteTest extends TestCase
{
    public function test_dependency_injection_in_octane_routes()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/autowire', 'GET'),
        ]);

        $stub = new OctaneRouteTestDependency();

        $app->bind(OctaneRouteTestDependency::class, fn () => $stub);

        $app['octane']->route('GET', '/autowire', function (OctaneRouteTestDependency $foo) {
            return new Response(spl_object_hash($foo));
        });

        $worker->run();

        $this->assertSame(spl_object_hash($stub), $client->responses[0]->getContent());
    }

    public function test_octane_routes_without_leading_slash()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('no_leading_slash', 'GET'),
        ]);

        $app['octane']->route('GET', 'no_leading_slash', fn () => new Response('no leading slash'));

        $worker->run();

        $this->assertSame('no leading slash', $client->responses[0]->getContent());
    }
}

class OctaneRouteTestDependency
{
}

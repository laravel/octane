<?php

namespace Laravel\Octane\Tests;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class AttributeSingletonPropagationTest extends TestCase
{
    public function test_singleton_attribute_classes_persist_across_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app['router']->get('/first', function (Application $app) {
            $instance = $app->make(AttributeSingletonService::class);

            return spl_object_hash($instance);
        });

        $worker->run();

        // All three requests should receive the same singleton instance
        // because the PropagateAttributeSingletons listener copies the
        // binding and instance back to the root application.
        $this->assertEquals(
            $client->responses[0]->original,
            $client->responses[1]->original,
        );

        $this->assertEquals(
            $client->responses[1]->original,
            $client->responses[2]->original,
        );
    }

    public function test_singleton_attribute_constructor_only_called_once()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        // Reset static counter.
        AttributeSingletonWithCounter::$constructCount = 0;

        $app['router']->get('/first', function (Application $app) {
            $app->make(AttributeSingletonWithCounter::class);

            return AttributeSingletonWithCounter::$constructCount;
        });

        $worker->run();

        // First request: constructor called once.
        $this->assertEquals(1, $client->responses[0]->original);
        // Second request: still only called once (same instance reused).
        $this->assertEquals(1, $client->responses[1]->original);
    }

    public function test_multiple_resolves_in_same_request_return_same_instance()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
        ]);

        $app['router']->get('/first', function (Application $app) {
            $a = $app->make(AttributeSingletonService::class);
            $b = $app->make(AttributeSingletonService::class);
            $c = $app->make(AttributeSingletonService::class);

            return [
                spl_object_hash($a),
                spl_object_hash($b),
                spl_object_hash($c),
            ];
        });

        $worker->run();

        $hashes = $client->responses[0]->original;
        $this->assertEquals($hashes[0], $hashes[1]);
        $this->assertEquals($hashes[1], $hashes[2]);
    }
}

#[Singleton]
class AttributeSingletonService
{
    //
}

#[Singleton]
class AttributeSingletonWithCounter
{
    public static int $constructCount = 0;

    public function __construct()
    {
        static::$constructCount++;
    }
}

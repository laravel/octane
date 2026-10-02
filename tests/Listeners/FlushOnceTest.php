<?php

namespace Laravel\Octane\Listeners;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Once;
use Laravel\Octane\Stream;
use Laravel\Octane\Tests\TestCase;
use Mockery;

class FlushOnceTest extends TestCase
{
    public function test_once_is_flushed()
    {
        if (! class_exists(Once::class)) {
            $this->markTestSkipped('Once is only supported in Laravel 11+');
        }

        [$app, $worker] = $this->createOctaneContext([
            Request::create('/', 'GET'),
            Request::create('/', 'GET'),
            Request::create('/', 'GET'),
        ]);

        $results = [];

        $app['router']->middleware('web')->get('/', function () use (&$results) {
            $results[] = my_rand();
        });

        $worker->run();

        $this->assertTrue($results[0] !== $results[1]);
        $this->assertTrue($results[0] !== $results[2]);
        $this->assertTrue($results[1] !== $results[2]);
    }

    public function test_once_is_flushed_when_previous_request_was_not_terminated()
    {
        if (! class_exists(Once::class)) {
            $this->markTestSkipped('Once is only supported in Laravel 11+');
        }

        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/', 'GET'),
            Request::create('/', 'GET'),
        ]);

        $results = [];

        $app['router']->middleware('web')->get('/', function () use (&$results) {
            $results[] = my_rand();
        });

        $failed = false;

        $worker->onRequestHandled(function () use (&$failed) {
            if (! $failed) {
                $failed = true;

                throw new Exception('Something went wrong.');
            }
        });

        Mockery::mock('alias:'.Stream::class)->shouldReceive('throwable')->once();

        $worker->run();

        $this->assertCount(2, $results);
        $this->assertTrue($results[0] !== $results[1]);
    }
}

function my_rand()
{
    return once(fn () => rand(1, PHP_INT_MAX));
}

<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Once;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Stream;
use Laravel\Octane\Testing\Fakes\FakeClient;
use Mockery;
use RuntimeException;

class WorkerTest extends TestCase
{
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

    public function test_worker_can_dispatch_task_to_application_and_returns_responses_to_client()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            fn () => 'foo',
            fn () => 'bar',
            function () {
            },
        ]);

        $responses = $worker->runTasks();

        $this->assertEquals('foo', $responses[0]->result);
        $this->assertEquals('bar', $responses[1]->result);
        $this->assertNull($responses[2]->result);
    }

    public function test_worker_can_dispatch_ticks_to_application_and_returns_responses_to_client()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            null,
            null,
        ]);

        $worker->runTicks();

        $this->assertTrue(true);
    }

    public function test_worker_doesnt_throw_buffer_error()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/test'),
        ]);

        $app['router']->get('/test', function () {
            $baselineBufferLevel = ob_get_level();

            while (ob_get_level() > $baselineBufferLevel) {
                ob_end_clean();
            }

            return 'Test Response';
        });

        $worker->run();

        $this->assertCount(1, $client->responses);
        $this->assertEquals('Test Response', $client->responses[0]->getContent());
    }

    public function test_worker_terminates_request_when_client_fails_to_respond()
    {
        if (! class_exists(Once::class)) {
            $this->markTestSkipped('Once is only supported in Laravel 11+');
        }

        $client = new class([
            Request::create('/once?value=first'),
            Request::create('/once?value=second'),
            Request::create('/once?value=third'),
        ]) extends FakeClient
        {
            public function respond(RequestContext $context, OctaneResponse $octaneResponse): void
            {
                if (++$this->index === 1) {
                    throw new RuntimeException('Client went away.');
                }

                parent::respond($context, $octaneResponse);
            }
        };

        [$app, $worker] = $this->createOctaneContext([], $client);

        Mockery::mock('alias:'.Stream::class)
            ->shouldReceive('throwable')
            ->once()
            ->with(Mockery::type(RuntimeException::class));

        $app['router']->get('/once', fn (Request $request) => once(fn () => $request->query('value')));

        $terminated = 0;

        $app['events']->listen(RequestTerminated::class, function () use (&$terminated) {
            $terminated++;
        });

        $worker->run();

        $this->assertSame(3, $terminated);
        $this->assertCount(1, $client->errors);
        $this->assertSame(['second', 'third'], array_map(fn ($response) => $response->getContent(), $client->responses));
    }
}

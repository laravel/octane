<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Http\Request;
use Laravel\Octane\Tests\TestCase;

use function Livewire\invade;

class GiveNewApplicationInstanceToEventDispatcherTest extends TestCase
{
    public function test_class_listeners_are_resolved_with_the_sandbox()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app->scoped(ScopedStateForEventListener::class, fn () => new ScopedStateForEventListener);

        $app['events']->listen('octane.test', ListenerWithScopedState::class);

        $route = function () {
            return [
                spl_object_id(app(ScopedStateForEventListener::class)),
                app('events')->dispatch('octane.test')[0],
            ];
        };

        $app['router']->middleware('web')->get('/first', $route);
        $app['router']->middleware('web')->get('/second', $route);

        $worker->run();

        $this->assertEquals($client->responses[0]->getData()[0], $client->responses[0]->getData()[1]);
        $this->assertEquals($client->responses[1]->getData()[0], $client->responses[1]->getData()[1]);
    }

    public function test_the_worker_application_is_given_back_once_the_request_is_over()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/first', function () {
            return [
                spl_object_id(app()),
                spl_object_id(invade(app('events'))->container),
            ];
        });

        $worker->run();

        $this->assertEquals($client->responses[0]->getData()[0], $client->responses[0]->getData()[1]);
        $this->assertSame($app, invade($app['events'])->container);
    }
}

class ScopedStateForEventListener
{
}

class ListenerWithScopedState
{
    public function __construct(public ScopedStateForEventListener $state)
    {
    }

    public function handle()
    {
        return spl_object_id($this->state);
    }
}

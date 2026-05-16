<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Routing\Route;
use Laravel\Octane\Tests\TestCase;

use function Livewire\invade;

class GiveNewApplicationInstanceToRouterTest extends TestCase
{
    public function test_router_has_new_instance()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/first', function () {
            $route = collect(app('router')->getRoutes()->getRoutes())->firstWhere(fn (Route $route) => $route->uri() === 'second');

            return [
                spl_object_id(app()),
                spl_object_id(invade($route)->container),
            ];
        });

        $app['router']->middleware('web')->get('/second', function () {
            $route = collect(app('router')->getRoutes()->getRoutes())->firstWhere(fn (Route $route) => $route->uri() === 'first');

            return [
                spl_object_id(app()),
                spl_object_id(invade($route)->container),
            ];
        });

        $worker->run();

        $this->assertEquals($client->responses[0]->getData()[0], $client->responses[0]->getData()[1]);
        $this->assertEquals($client->responses[1]->getData()[0], $client->responses[1]->getData()[1]);
    }

    public function test_router_has_new_instance_when_routes_are_compiled()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/first', function () {
            $route = collect(app('router')->getRoutes()->getRoutes())->firstWhere(fn (Route $route) => $route->uri() === 'second');

            return [
                spl_object_id(app()),
                spl_object_id(invade($route)->container),
                app('router')->getRoutes()::class,
            ];
        })->name('first');

        $app['router']->middleware('web')->get('/second', function () {
            $route = collect(app('router')->getRoutes()->getRoutes())->firstWhere(fn (Route $route) => $route->uri() === 'first');

            return [
                spl_object_id(app()),
                spl_object_id(invade($route)->container),
                app('router')->getRoutes()::class,
            ];
        })->name('second');

        $app['router']->setCompiledRoutes($app['router']->getRoutes()->compile());

        $worker->run();

        $this->assertSame(CompiledRouteCollection::class, $client->responses[0]->getData()[2]);
        $this->assertSame(CompiledRouteCollection::class, $client->responses[1]->getData()[2]);
        $this->assertEquals($client->responses[0]->getData()[0], $client->responses[0]->getData()[1]);
        $this->assertEquals($client->responses[1]->getData()[0], $client->responses[1]->getData()[1]);
    }

    public function test_router_has_new_instance_when_compiled_route_name_cache_is_warmed()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/first', function () {
            $route = app('router')->getRoutes()->getByName('second');

            return [
                spl_object_id(app()),
                spl_object_id(invade($route)->container),
                app('router')->getRoutes()::class,
            ];
        })->name('first');

        $app['router']->middleware('web')->get('/second', function () {
            $route = app('router')->getRoutes()->getByName('first');

            return [
                spl_object_id(app()),
                spl_object_id(invade($route)->container),
                app('router')->getRoutes()::class,
            ];
        })->name('second');

        $app['router']->setCompiledRoutes($app['router']->getRoutes()->compile());

        $app['router']->getRoutes()->getByName('first');
        $app['router']->getRoutes()->getByName('second');

        $worker->run();

        $this->assertSame(CompiledRouteCollection::class, $client->responses[0]->getData()[2]);
        $this->assertSame(CompiledRouteCollection::class, $client->responses[1]->getData()[2]);
        $this->assertEquals($client->responses[0]->getData()[0], $client->responses[0]->getData()[1]);
        $this->assertEquals($client->responses[1]->getData()[0], $client->responses[1]->getData()[1]);
    }
}

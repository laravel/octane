<?php

namespace Laravel\Octane\Concerns;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

trait ProvidesRouting
{
    /**
     * All of the registered Octane routes.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * Register a Octane route.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  \Closure  $callback
     * @return void
     */
    public function route(string $method, string $uri, Closure $callback): void
    {
        $route = $method . Str::start($uri, '/');

        $this->routes[$route] = $callback;
    }

    /**
     * Determine if a route exists for the given method and URI.
     *
     * @param  string  $method
     * @param  string  $uri
     * @return bool
     */
    public function hasRouteFor(string $method, string $uri): bool
    {
        $route = $method . Str::start($uri, '/');

        return isset($this->routes[$route]);
    }

    /**
     * Invoke the route for the given method and URI.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $method
     * @param  string  $uri
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function invokeRoute(Request $request, string $method, string $uri): Response
    {
        $route = $method . Str::start($uri, '/');

        return Container::getInstance()->call($this->routes[$route]);
    }
}

<?php

namespace Laravel\Octane\Concerns;

use Illuminate\Container\Container;
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
     * Register an Octane route.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array|string|callable  $action
     * @return void
     */
    public function route(string $method, string $uri, array | string | callable $action): void
    {
        $route = $method.Str::start($uri, '/');

        $this->routes[$route] = $action;
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
        $route = $method.Str::start($uri, '/');

        return isset($this->routes[$route]);
    }

    /**
     * Invoke the route for the given method and URI.
     *
     * @param  string  $method
     * @param  string  $uri
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function invokeRoute(string $method, string $uri): Response
    {
        $route = $method.Str::start($uri, '/');

        return Container::getInstance()->call($this->routes[$route]);
    }
}

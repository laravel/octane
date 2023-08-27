<?php

namespace Laravel\Octane\Concerns;

use Closure;
use Illuminate\Http\Request;
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
     */
    public function route(string $method, string $uri, Closure $callback): void
    {
        $this->routes[$method.$uri] = $callback;
    }

    /**
     * Determine if a route exists for the given method and URI.
     */
    public function hasRouteFor(string $method, string $uri): bool
    {
        return isset($this->routes[$method.$uri]);
    }

    /**
     * Invoke the route for the given method and URI.
     */
    public function invokeRoute(Request $request, string $method, string $uri): Response
    {
        return call_user_func($this->routes[$method.$uri], $request);
    }

    /**
     * Get the registered Octane routes.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}

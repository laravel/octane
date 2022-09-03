<?php

namespace Laravel\Octane\Contracts;

use Closure;
use Illuminate\Http\Request;
use Laravel\Octane\Exceptions\TaskException;
use Laravel\Octane\Exceptions\TaskTimeoutException;
use Swoole\Table;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

interface Octane {
    /**
     * Concurrently resolve the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     * @param  array  $tasks
     * @param  int  $waitMilliseconds
     * @return array
     *
     * @throws TaskException
     * @throws TaskTimeoutException
     */
    public function concurrently(array $tasks, int $waitMilliseconds = 3000): array;

    /**
     * Get the task dispatcher.
     *
     * @return DispatchesTasks
     */
    public function tasks(): DispatchesTasks;

    /**
     * Get the listeners that will prepare the Laravel application for a new request.
     *
     * @return array
     */
    public static function prepareApplicationForNextRequest(): array;

    /**
     * Get the listeners that will prepare the Laravel application for a new operation.
     *
     * @return array
     */
    public static function prepareApplicationForNextOperation(): array;

    /**
     * Get the container bindings / services that should be pre-resolved by default.
     *
     * @return array
     */
    public static function defaultServicesToWarm(): array;

    /**
     * Register a Octane route.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  Closure  $callback
     * @return void
     */
    public function route(string $method, string $uri, Closure $callback): void;

    /**
     * Determine if a route exists for the given method and URI.
     *
     * @param  string  $method
     * @param  string  $uri
     * @return bool
     */
    public function hasRouteFor(string $method, string $uri): bool;

    /**
     * Invoke the route for the given method and URI.
     *
     * @param  Request  $request
     * @param  string  $method
     * @param  string  $uri
     * @return Response
     */
    public function invokeRoute(Request $request, string $method, string $uri): Response;

    /**
     * Register a callback to be called every N seconds.
     *
     * @param  string  $key
     * @param  callable  $callback
     * @param  int  $seconds
     * @param  bool  $immediate
     * @return \Laravel\Octane\Swoole\InvokeTickCallable
     */
    public function tick(string $key, callable $callback, int $seconds = 1, bool $immediate = true);

    /**
     * Get a Swoole table instance.
     *
     * @param  string  $table
     * @return Table
     */
    public function table(string $table): Table;

    /**
     * Format an exception to a string that should be returned to the client.
     *
     * @param  Throwable  $e
     * @param  bool  $debug
     * @return string
     */
    public static function formatExceptionForClient(Throwable $e, bool $debug = false): string;
}

<?php

namespace Laravel\Octane\Facades;

use Illuminate\Support\Facades\Facade;
use Laravel\Octane\Contracts\Octane as OctaneContract;

/**
 * @method static \Laravel\Octane\Swoole\InvokeTickCallable tick(string $key, callable $callback, int $seconds = 1, bool $immediate = true)
 * @method static \Swoole\Table table(string $name)
 * @method static \Symfony\Component\HttpFoundation\Response invokeRoute(\Illuminate\Http\Request $request, string $method, string $uri)
 * @method static array concurrently(array $tasks, int $waitMilliseconds = 3000)
 * @method static bool hasRouteFor(string $method, string $uri)
 * @method static void route(string $method, string $uri, \Closure $callback)
 *
 * @see \Laravel\Octane\Octane
 */
class Octane extends Facade
{
    protected static function getFacadeAccessor()
    {
        return OctaneContract::class;
    }
}

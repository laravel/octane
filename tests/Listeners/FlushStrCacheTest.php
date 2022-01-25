<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Octane\Tests\TestCase;
use ReflectionClass;

class FlushStrCacheTest extends TestCase
{
    public function test_str_is_flushed()
    {
        Str::flushCache();

        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/test-str-cache', 'GET'),
            Request::create('/', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/', function () {
            return 'Hello World';
        });

        $app['router']->middleware('web')->get('/test-str-cache', function () {
            return Str::snake('Taylor Otwell');
        });

        $reflection = new ReflectionClass(Str::class);
        $property = $reflection->getProperty('snakeCache');
        $property->setAccessible(true);

        $this->assertEmpty($property->getValue());

        $worker->run();

        $this->assertEmpty($property->getValue());
    }
}

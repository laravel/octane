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

        $app['router']->middleware('web')->get('/test-str-cache', function () {
            return Str::snake('Taylor Otwell');
        });

        $app['router']->middleware('web')->get('/', function () {
            $reflection = new ReflectionClass(Str::class);
            $property = $reflection->getProperty('snakeCache');

            return empty($property->getValue()) ? 'cache-is-empty' : 'cache-is-not-empty';
        });

        $worker->run();

        $this->assertSame('taylor_otwell', $client->responses[0]->getContent());
        $this->assertSame('cache-is-empty', $client->responses[1]->getContent());
    }
}

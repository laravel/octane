<?php

namespace Laravel\Octane\Tests\Listeners;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Laravel\Octane\Tests\TestCase;

class GiveNewApplicationInstanceToLogManagerTest extends TestCase
{
    public function test_context_is_appened_to_logs()
    {
        if (! class_exists(Context::class)) {
            $this->markTestSkipped('Context is not available in this version of Laravel.');
        }
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/', 'GET'),
        ]);
        $path = $app['config']->get('logging.channels.single.path');
        @unlink($path);

        $this->assertFileDoesNotExist($path);

        $app['router']->middleware('web')->get('/', function () {
            Context::add('foo', 'bar');
            Log::info('Hello world');
        });

        $worker->run();

        $this->assertStringContainsString('{"foo":"bar"}', file_get_contents($path));
    }
}

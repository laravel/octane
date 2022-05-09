<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Laravel\Octane\Tests\TestCase;
use Mockery;
use Monolog;

class FlushLogContextTest extends TestCase
{
    public function test_shared_context_is_flushed()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/', 'GET'),
        ]);
        $app['router']->middleware('web')->get('/', function () {
            // ..
        });
        $log = $app['log'];
        $log->shareContext(['shared' => 'context']);

        $this->assertSame(['shared' => 'context'], $log->sharedContext());

        $worker->run();

        $this->assertSame([], $log->sharedContext());
    }
}

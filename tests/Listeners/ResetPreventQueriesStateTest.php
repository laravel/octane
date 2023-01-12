<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Http\Request;
use Laravel\Octane\Tests\TestCase;

class ResetPreventQueriesStateTest extends TestCase
{
    public function test_it_resets_prevents_queries_state()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/', 'GET'),
        ]);
        $app['router']->middleware('web')->get('/', function () {
            //
        });
        $connection = $app['db']->connection();

        $connection->preventQueries();
        $this->assertTrue($connection->preventingQueries());

        $worker->run();
        $this->assertSame(false, $connection->preventingQueries());
    }
}




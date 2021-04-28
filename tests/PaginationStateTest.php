<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

class PaginationStateTest extends TestCase
{
    public function test_pagination_state_is_updated_across_subsequent_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first?page=1', 'GET'),
            Request::create('/second?page=2', 'GET'),
        ]);

        $app['router']->get('/first', function (Application $app) {
            return Paginator::resolveCurrentPath().'-'.Paginator::resolveCurrentPage();
        });

        $app['router']->get('/second', function (Application $app) {
            return Paginator::resolveCurrentPath().'-'.Paginator::resolveCurrentPage();
        });

        $worker->run();

        $this->assertEquals('http://localhost/first-1', $client->responses[0]->getContent());
        $this->assertEquals('http://localhost/second-2', $client->responses[1]->getContent());
    }
}

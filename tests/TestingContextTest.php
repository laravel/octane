<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Laravel\Octane\Testing\OctaneRequest;

class TestingContextTest extends \Orchestra\Testbench\TestCase
{
    protected function defineRoutes($router)
    {
        $router->middleware('web')->get('/first', function (Request $request) {
            return $request->url();
        });

        $router->middleware('web')->get('/second', function (Request $request) {
            return $request->url();
        });
    }

    /** @test */
    public function test_router_and_route_container_is_refreshed_across_subsequent_requests()
    {
        $responses = OctaneRequest::from($this->app)->handle([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $this->assertInstanceOf(TestResponse::class, $responses[0]);
        $this->assertInstanceOf(TestResponse::class, $responses[1]);

        $this->assertEquals('http://localhost/first', $responses[0]->getContent());
        $this->assertEquals('http://localhost/second', $responses[1]->getContent());
    }
}

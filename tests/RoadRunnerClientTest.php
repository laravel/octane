<?php

namespace Laravel\Octane\Tests;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laminas\Diactoros\ServerRequestFactory;
use Laravel\Octane\RequestContext;
use Laravel\Octane\RoadRunner\RoadRunnerClient;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\PSR7Client;

class RoadRunnerClientTest extends TestCase
{
    /** @test */
    public function test_marshal_request_method_marshals_proper_illuminate_request()
    {
        $client = new RoadRunnerClient(Mockery::mock(PSR7Client::class));

        $psr7Request = (new ServerRequestFactory)->createServerRequest('GET', '/home');
        $psr7Request = $psr7Request->withQueryParams(['name' => 'Taylor']);

        [$request, $context] = $client->marshalRequest(new RequestContext([
            'psr7Request' => $psr7Request,
        ]));

        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('Taylor', $request->query('name'));
    }

    /** @doesNotPerformAssertions @test */
    public function test_respond_method_send_response_to_roadrunner()
    {
        $client = new RoadRunnerClient($psr7Client = Mockery::mock(PSR7Client::class));

        $psr7Request = (new ServerRequestFactory)->createServerRequest('GET', '/home');
        $psr7Request = $psr7Request->withQueryParams(['name' => 'Taylor']);

        $psr7Client->shouldReceive('respond')->once()->with(Mockery::type(ResponseInterface::class));

        $client->respond(new RequestContext([
            'psr7Request' => $psr7Request,
        ]), new Response('Hello World', 200));
    }

    /** @doesNotPerformAssertions @test */
    public function test_error_method_sends_error_response_to_roadrunner()
    {
        $psr7Client = Mockery::mock(PSR7Client::class);
        $psr7Client->shouldReceive('getWorker->error')->once()->with('Internal server error.');

        $client = new RoadRunnerClient($psr7Client);

        $app = $this->createApplication();
        $request = Request::create('/', 'GET');
        $context = new RequestContext;

        $client->error(new Exception('Something went wrong...'), $app, $request, $context);
    }

    /** @doesNotPerformAssertions @test */
    public function test_error_method_sends_detailed_error_response_to_roadrunner_in_debug_mode()
    {
        $e = new Exception('Something went wrong...');

        $psr7Client = Mockery::mock(PSR7Client::class);
        $psr7Client->shouldReceive('getWorker->error')->once()->with((string) $e);

        $client = new RoadRunnerClient($psr7Client);

        $app = $this->createApplication();
        $app['config']['app.debug'] = true;

        $request = Request::create('/', 'GET');
        $context = new RequestContext;

        $client->error($e, $app, $request, $context);
    }
}

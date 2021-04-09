<?php

namespace Laravel\Octane\Tests;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Swoole\SwooleClient;
use Mockery;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SwooleClientTest extends TestCase
{
    /** @test */
    public function test_marshal_request_method_marshals_proper_illuminate_request()
    {
        $client = new SwooleClient;

        $swooleRequest = new class {
            public $get = [
                'name' => 'Taylor',
            ];

            public $cookie = [
                'color' => 'blue',
            ];

            public $server = [
                'HTTP_HOST' => 'localhost',
                'PATH_INFO' => '/foo/bar',
                'REMOTE_ADDR' => '127.0.0.1',
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/foo/bar?name=Taylor',
            ];

            public function rawContent()
            {
                return 'Hello World';
            }
        };

        [$request, $context] = $client->marshalRequest($givenContext = new RequestContext([
            'swooleRequest' => $swooleRequest,
        ]));

        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('Hello World', $request->getContent());
        $this->assertEquals('127.0.0.1', $request->ip());
        $this->assertEquals('foo/bar', $request->path());
        $this->assertEquals('Taylor', $request->query('name'));
        $this->assertEquals('blue', $request->cookies->get('color'));
        $this->assertSame($givenContext, $context);
    }

    /** @test */
    public function test_can_serve_static_files_if_configured_to_and_file_is_within_public_directory()
    {
        $client = new SwooleClient;

        $request = Request::create('/foo.txt', 'GET');

        $context = new RequestContext([
            'publicPath' => __DIR__.'/public',
        ]);

        $this->assertTrue($client->canServeRequestAsStaticFile($request, $context));
    }

    /** @test */
    public function test_cant_serve_static_files_if_file_is_outside_public_directory()
    {
        $client = new SwooleClient;

        $request = Request::create('/../foo.txt', 'GET');

        $context = new RequestContext([
            'publicPath' => __DIR__.'/public/files',
        ]);

        $this->assertFalse($client->canServeRequestAsStaticFile($request, $context));
    }

    /** @test */
    public function test_cant_serve_static_files_if_file_has_forbidden_extension()
    {
        $client = new SwooleClient;

        $request = Request::create('/foo.php', 'GET');

        $context = new RequestContext([
            'publicPath' => __DIR__.'/public/files',
        ]);

        $this->assertFalse($client->canServeRequestAsStaticFile($request, $context));
    }

    /** @doesNotPerformAssertions @test */
    public function test_static_file_can_be_served()
    {
        $client = new SwooleClient;

        $request = Request::create('/foo.txt', 'GET');

        $context = new RequestContext([
            'swooleResponse' => $swooleResponse = Mockery::mock('stdClass'),
            'publicPath' => __DIR__.'/public',
        ]);

        $swooleResponse->shouldReceive('status')->once()->with(200);
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/plain');
        $swooleResponse->shouldReceive('sendfile')->once()->with(realpath(__DIR__.'/public/foo.txt'));

        $client->serveStaticFile($request, $context);
    }

    /** @doesNotPerformAssertions @test */
    public function test_respond_method_send_response_to_swoole()
    {
        $client = new SwooleClient;

        $swooleResponse = Mockery::mock('Swoole\Http\Response');

        $swooleResponse->shouldReceive('status')->once()->with(200);
        $swooleResponse->shouldReceive('header')->once()->with('Cache-Control', 'no-cache, private');
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/html');
        $swooleResponse->shouldReceive('header')->once()->with('Date', Mockery::type('string'));
        $swooleResponse->shouldReceive('end')->once()->with('Hello World');

        $client->respond(new RequestContext([
            'swooleResponse' => $swooleResponse,
        ]), new Response('Hello World', 200, ['Content-Type' => 'text/html']));
    }

    /** @doesNotPerformAssertions @test */
    public function test_respond_method_send_streamed_response_to_swoole()
    {
        $client = new SwooleClient;

        $swooleResponse = Mockery::mock('Swoole\Http\Response');

        $swooleResponse->shouldReceive('status')->once()->with(200);
        $swooleResponse->shouldReceive('header')->once()->with('Cache-Control', 'no-cache, private');
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/html');
        $swooleResponse->shouldReceive('header')->once()->with('Date', Mockery::type('string'));
        $swooleResponse->shouldReceive('end')->once()->with('Hello World');

        $client->respond(new RequestContext([
            'swooleResponse' => $swooleResponse,
        ]), new StreamedResponse(function () {
            echo 'Hello World';
        }, 200, ['Content-Type' => 'text/html']));
    }

    /** @doesNotPerformAssertions @test */
    public function test_error_method_sends_error_response_to_swoole()
    {
        $client = new SwooleClient;

        $swooleResponse = Mockery::spy('Swoole\Http\Response');

        $app = $this->createApplication();
        $request = Request::create('/', 'GET');
        $context = new RequestContext(['swooleResponse' => $swooleResponse]);

        $client->error(new Exception('Something went wrong...'), $app, $request, $context);

        $swooleResponse->shouldHaveReceived('header')->with('Status', '500 Internal Server Error');
        $swooleResponse->shouldHaveReceived('header')->with('Content-Type', 'text/plain');
        $swooleResponse->shouldHaveReceived('end')->with('Internal server error.');
    }

    /** @doesNotPerformAssertions @test */
    public function test_error_method_sends_detailed_error_response_to_swoole_in_debug_mode()
    {
        $client = new SwooleClient;

        $swooleResponse = Mockery::spy('Swoole\Http\Response');

        $app = $this->createApplication();
        $app['config']['app.debug'] = true;

        $request = Request::create('/', 'GET');
        $context = new RequestContext(['swooleResponse' => $swooleResponse]);

        $client->error($e = new Exception('Something went wrong...'), $app, $request, $context);

        $swooleResponse->shouldHaveReceived('header')->with('Status', '500 Internal Server Error');
        $swooleResponse->shouldHaveReceived('header')->with('Content-Type', 'text/plain');
        $swooleResponse->shouldHaveReceived('end')->with((string) $e);
    }
}

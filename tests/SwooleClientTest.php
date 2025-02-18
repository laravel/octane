<?php

namespace Laravel\Octane\Tests;

use Config;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Swoole\SwooleClient;
use Mockery;
use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SwooleClientTest extends TestCase
{
    public function test_marshal_request_method_marshals_proper_illuminate_request(): void
    {
        $client = new SwooleClient;

        $swooleRequest = new class
        {
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
                'REQUEST_URI' => '/foo/bar',
                'QUERY_STRING' => 'name=Taylor',
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
        $this->assertEquals('/foo/bar?name=Taylor', $request->getRequestUri());
        $this->assertEquals('Taylor', $request->query('name'));
        $this->assertEquals('blue', $request->cookies->get('color'));
        $this->assertSame($givenContext, $context);
    }

    public function test_can_serve_static_files_if_configured_to_and_file_is_within_public_directory(): void
    {
        $client = new SwooleClient;

        $request = Request::create('/foo.txt', 'GET');

        $context = new RequestContext([
            'publicPath' => __DIR__.'/public',
            'octaneConfig' => [],
        ]);

        $this->assertTrue($client->canServeRequestAsStaticFile($request, $context));
    }

    public function test_cant_serve_static_files_if_file_is_outside_public_directory(): void
    {
        $client = new SwooleClient;

        $request = Request::create('/../foo.txt', 'GET');

        $context = new RequestContext([
            'publicPath' => __DIR__.'/public/files',
            'octaneConfig' => [],
        ]);

        $this->assertFalse($client->canServeRequestAsStaticFile($request, $context));
    }

    public function test_cant_serve_static_files_if_file_has_forbidden_extension(): void
    {
        $client = new SwooleClient;

        $request = Request::create('/foo.php', 'GET');

        $context = new RequestContext([
            'publicPath' => __DIR__.'/public/files',
            'octaneConfig' => [],
        ]);

        $this->assertFalse($client->canServeRequestAsStaticFile($request, $context));
    }

    /** @doesNotPerformAssertions @test */
    public function test_static_file_can_be_served(): void
    {
        $client = new SwooleClient;

        $request = Request::create('/foo.txt', 'GET');

        $context = new RequestContext([
            'swooleResponse' => $swooleResponse = Mockery::mock('stdClass'),
            'publicPath' => __DIR__.'/public',
            'octaneConfig' => [],
        ]);

        $swooleResponse->shouldReceive('status')->once()->with(200);
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/plain');
        $swooleResponse->shouldReceive('sendfile')->once()->with(realpath(__DIR__.'/public/foo.txt'));

        $client->serveStaticFile($request, $context);
    }

    /** @doesNotPerformAssertions @test */
    public function test_static_file_headers_can_be_sent(): void
    {
        $this->createApplication();
        $client = new SwooleClient;

        $request = Request::create('/foo.txt', 'GET');

        $context = new RequestContext([
            'swooleResponse' => $swooleResponse = Mockery::mock('stdClass'),
            'publicPath' => __DIR__.'/public',
            'octaneConfig' => [
                'static_file_headers' => [
                    'foo.txt' => [
                        'X-Test-Header' => 'Valid',
                    ],
                ],
            ],
        ]);

        $swooleResponse->shouldReceive('status')->once()->with(200);
        $swooleResponse->shouldReceive('header')->once()->with('X-Test-Header', 'Valid', true);
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/plain');
        $swooleResponse->shouldReceive('sendfile')->once()->with(realpath(__DIR__.'/public/foo.txt'));

        $client->serveStaticFile($request, $context);
    }

    public function test_can_serve_static_files_through_symlink(): void
    {
        $client = new SwooleClient;

        $request = Request::create('/symlink/foo.txt', 'GET');

        $context = new RequestContext([
            'publicPath' => __DIR__.'/public/files',
            'octaneConfig' => [],
        ]);

        $this->assertTrue($client->canServeRequestAsStaticFile($request, $context));
    }

    public function test_cant_serve_static_files_through_symlink_using_directory_traversal(): void
    {
        $client = new SwooleClient;

        $request = Request::create('/symlink/../foo.txt', 'GET');

        $context = new RequestContext([
            'publicPath' => __DIR__.'/public/files',
            'octaneConfig' => [],
        ]);

        $this->assertFalse($client->canServeRequestAsStaticFile($request, $context));
    }

    /** @doesNotPerformAssertions @test */
    public function test_respond_method_sends_response_to_swoole(): void
    {
        $this->createApplication();

        $client = new SwooleClient;

        $swooleResponse = Mockery::mock('Swoole\Http\Response');

        $swooleResponse->shouldReceive('status')->once()->with(200);
        $swooleResponse->shouldReceive('header')->once()->with('Cache-Control', 'no-cache, private', true);
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/html', true);
        $swooleResponse->shouldReceive('header')->once()->with('Date', Mockery::type('string'), true);
        if (extension_loaded('swoole') && SWOOLE_VERSION_ID >= 60000) {
            $swooleResponse->shouldReceive('cookie')->once()->with('new', 'value', 0, '/', '', false, true, 'lax', '', false);
            $swooleResponse->shouldReceive('cookie')->once()->with('cleared', 'deleted', Mockery::type('int'), '/', '', false, true, 'lax', '', false);
        } else {
            $swooleResponse->shouldReceive('cookie')->once()->with('new', 'value', 0, '/', '', false, true, 'lax');
            $swooleResponse->shouldReceive('cookie')->once()->with('cleared', 'deleted', Mockery::type('int'), '/', '', false, true, 'lax');
        }
        $swooleResponse->shouldReceive('write')->with('Hello World');
        $swooleResponse->shouldReceive('end')->once();

        $response = new Response('Hello World', 200, ['Content-Type' => 'text/html']);
        $response->cookie('new', 'value');
        $response->withoutCookie('cleared');

        $client->respond(new RequestContext([
            'swooleResponse' => $swooleResponse,
        ]), new OctaneResponse($response));
    }

    /** @doesNotPerformAssertions @test */
    public function test_respond_method_send_streamed_response_to_swoole(): void
    {
        $this->createApplication();
        $client = new SwooleClient;

        $swooleResponse = Mockery::mock('Swoole\Http\Response');

        $swooleResponse->shouldReceive('status')->once()->with(200);
        $swooleResponse->shouldReceive('header')->once()->with('Cache-Control', 'no-cache, private', true);
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/html', true);
        $swooleResponse->shouldReceive('header')->once()->with('Date', Mockery::type('string'), true);
        $swooleResponse->shouldReceive('write')->once()->with('Hello World');
        $swooleResponse->shouldReceive('end')->once();

        $client->respond(new RequestContext([
            'swooleResponse' => $swooleResponse,
        ]), new OctaneResponse(new StreamedResponse(function () {
            echo 'Hello World';
        }, 200, ['Content-Type' => 'text/html'])));
    }

    /** @doesNotPerformAssertions @test */
    public function test_respond_method_with_laravel_specific_status_code_sends_response_to_swoole(): void
    {
        $this->createApplication();
        $client = new SwooleClient;

        $swooleResponse = Mockery::mock('Swoole\Http\Response');

        $swooleResponse->shouldReceive('status')->once()->with(419, 'Page Expired');
        $swooleResponse->shouldReceive('header')->once()->with('Cache-Control', 'no-cache, private', true);
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/html', true);
        $swooleResponse->shouldReceive('header')->once()->with('Date', Mockery::type('string'), true);
        $swooleResponse->shouldReceive('write')->with('Hello World');
        $swooleResponse->shouldReceive('end')->once();

        $client->respond(new RequestContext([
            'swooleResponse' => $swooleResponse,
        ]), new OctaneResponse(new Response('Hello World', 419, ['Content-Type' => 'text/html'])));
    }

    /** @doesNotPerformAssertions @test */
    public function test_error_method_sends_error_response_to_swoole(): void
    {
        $client = new SwooleClient;

        $swooleResponse = Mockery::spy('Swoole\Http\Response');

        $app = $this->createApplication();
        $app['config']['app.debug'] = false;

        $request = Request::create('/', 'GET');
        $context = new RequestContext(['swooleResponse' => $swooleResponse]);

        $client->error(new Exception('Something went wrong...'), $app, $request, $context);

        $swooleResponse->shouldHaveReceived('header')->with('Status', '500 Internal Server Error');
        $swooleResponse->shouldHaveReceived('header')->with('Content-Type', 'text/plain');
        $swooleResponse->shouldHaveReceived('end')->with('Internal server error.');
    }

    /** @doesNotPerformAssertions @test */
    public function test_error_method_sends_detailed_error_response_to_swoole_in_debug_mode(): void
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

    /** @doesNotPerformAssertions @test */
    public function test_respond_method_send_not_chunked_response_to_swoole(): void
    {
        $this->createApplication();
        $client = new SwooleClient;

        $swooleResponse = Mockery::mock(SwooleResponse::class);

        $swooleResponse->shouldReceive('status')->once()->with(200);
        $swooleResponse->shouldReceive('header')->once()->with('Cache-Control', 'no-cache, private', true);
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/html', true);
        $swooleResponse->shouldReceive('header')->once()->with('Date', Mockery::type('string'), true);
        $swooleResponse->shouldReceive('write')->never();
        $swooleResponse->shouldReceive('end')->once()->with('Hello World');

        $response = new Response('Hello World', 200, ['Content-Type' => 'text/html']);

        $client->respond(new RequestContext([
            'swooleResponse' => $swooleResponse,
        ]), new OctaneResponse($response));
    }

    /** @doesNotPerformAssertions @test */
    public function test_respond_method_send_chunked_response_to_swoole(): void
    {
        $this->createApplication();
        $client = new SwooleClient(6);

        $swooleResponse = Mockery::mock('Swoole\Http\Response');

        $swooleResponse->shouldReceive('status')->once()->with(200);
        $swooleResponse->shouldReceive('header')->once()->with('Cache-Control', 'no-cache, private', true);
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/html', true);
        $swooleResponse->shouldReceive('header')->once()->with('Date', Mockery::type('string'), true);
        $swooleResponse->shouldReceive('write')->once()->with('Hello ');
        $swooleResponse->shouldReceive('write')->once()->with('World');
        $swooleResponse->shouldReceive('end')->once();

        $response = new Response('Hello World', 200, ['Content-Type' => 'text/html']);

        $client->respond(new RequestContext([
            'swooleResponse' => $swooleResponse,
        ]), new OctaneResponse($response));
    }

    /** @doesNotPerformAssertions @test */
    public function test_respond_method_preserves_header_formatting_if_configured(): void
    {
        $this->createApplication();

        $client = new SwooleClient;

        Config::set('octane.swoole.format_headers', false);

        $swooleResponse = Mockery::mock('Swoole\Http\Response');

        $swooleResponse->shouldReceive('status')->once()->with(200);
        $swooleResponse->shouldReceive('header')->once()->with('Cache-Control', 'no-cache, private', false);
        $swooleResponse->shouldReceive('header')->once()->with('Content-Type', 'text/html', false);
        $swooleResponse->shouldReceive('header')->once()->with('Date', Mockery::type('string'), false);
        $swooleResponse->shouldReceive('end')->once();

        $response = new Response(null, 200, ['Content-Type' => 'text/html']);

        $client->respond(new RequestContext([
            'swooleResponse' => $swooleResponse,
        ]), new OctaneResponse($response));
    }
}

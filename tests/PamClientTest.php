<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\Pam\PamClient;
use Laravel\Octane\RequestContext;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PamClientTest extends TestCase
{
    public function test_marshal_request()
    {
        $requestContext = new RequestContext;
        $marshaledRequest = (new PamClient)->marshalRequest($requestContext);
        $this->assertInstanceOf(Request::class, $marshaledRequest[0]);
        $this->assertSame($requestContext, $marshaledRequest[1]);
        $this->assertSame($marshaledRequest[0], $requestContext['request']);
    }

    public function test_response_is_sent_through_the_sapi()
    {
        $this->expectOutputString('Hello World');

        (new PamClient)->respond(new RequestContext, new OctaneResponse(new Response('Hello World')));
    }

    public function test_output_buffer_is_prepended_to_the_response()
    {
        $this->expectOutputString('dump Hello World');

        (new PamClient)->respond(new RequestContext, new OctaneResponse(new Response('Hello World'), 'dump '));
    }

    public function test_streamed_generator_response_is_written_chunk_by_chunk()
    {
        $response = new StreamedResponse(function () {
            yield 'Hello';
            yield 'World';
        }, 201, ['Content-Type' => 'text/event-stream']);

        $response->headers->setCookie(Cookie::create('name', 'Taylor'));

        (new PamClient)->respond(new RequestContext([
            'request' => Request::create('/', 'GET'),
            'pamResponse' => $pamResponse = new FakePamResponse,
        ]), new OctaneResponse($response));

        $this->assertSame(201, $pamResponse->status);
        $this->assertSame(['text/event-stream'], $pamResponse->headers['content-type']);
        $this->assertStringStartsWith('name=Taylor', $pamResponse->headers['set-cookie'][0]);
        $this->assertSame(['Hello', 'World'], $pamResponse->chunks);
    }

    public function test_streamed_response_to_head_request_has_no_body()
    {
        (new PamClient)->respond(new RequestContext([
            'request' => Request::create('/', 'HEAD'),
            'pamResponse' => $pamResponse = new FakePamResponse,
        ]), new OctaneResponse(new StreamedResponse(function () {
            throw new RuntimeException('The body should not be sent.');
        })));

        $this->assertSame(200, $pamResponse->status);
        $this->assertSame('', $pamResponse->body);
        $this->assertSame([], $pamResponse->chunks);
    }

    public function test_can_serve_static_files_within_public_directory()
    {
        $context = new RequestContext([
            'publicPath' => __DIR__.'/public',
            'octaneConfig' => [],
        ]);

        $this->assertTrue((new PamClient)->canServeRequestAsStaticFile(Request::create('/foo.txt', 'GET'), $context));
        $this->assertFalse((new PamClient)->canServeRequestAsStaticFile(Request::create('/files/symlink/', 'GET'), $context));
        $this->assertFalse((new PamClient)->canServeRequestAsStaticFile(Request::create('/foo.txt', 'POST'), $context));
    }

    public function test_cant_serve_static_files_when_disabled()
    {
        $context = new RequestContext([
            'publicPath' => __DIR__.'/public',
            'octaneConfig' => ['serve_static_files' => false],
        ]);

        $this->assertFalse((new PamClient)->canServeRequestAsStaticFile(Request::create('/foo.txt', 'GET'), $context));
    }

    public function test_cant_serve_static_files_outside_public_directory_or_with_forbidden_extension()
    {
        $context = new RequestContext([
            'publicPath' => __DIR__.'/public/files',
            'octaneConfig' => [],
        ]);

        $this->assertFalse((new PamClient)->canServeRequestAsStaticFile(Request::create('/%2E%2E/foo.txt', 'GET'), $context));
        $this->assertFalse((new PamClient)->canServeRequestAsStaticFile(Request::create('/foo.php', 'GET'), $context));
        $this->assertFalse((new PamClient)->canServeRequestAsStaticFile(Request::create('/symlink/', 'GET'), $context));
    }

    public function test_static_files_are_streamed_with_configured_headers()
    {
        $request = Request::create('/foo.txt', 'GET');

        (new PamClient)->serveStaticFile($request, new RequestContext([
            'publicPath' => __DIR__.'/public',
            'octaneConfig' => ['static_file_headers' => ['*.txt' => ['X-Test' => 'Taylor']]],
            'pamResponse' => $pamResponse = new FakePamResponse,
        ]));

        $this->assertSame(200, $pamResponse->status);
        $this->assertStringStartsWith('text/plain', $pamResponse->headers['content-type'][0]);
        $this->assertSame(['Taylor'], $pamResponse->headers['x-test']);
        $this->assertSame(file_get_contents(__DIR__.'/public/foo.txt'), implode('', $pamResponse->chunks));
    }

    public function test_error()
    {
        $this->expectOutputString('Internal server error.');

        $app = $this->createApplication();
        $app['config']->set('app.debug', false);

        (new PamClient)->error(new RuntimeException('Error'), $app, Request::create('/'), new RequestContext);
    }
}

class FakePamResponse
{
    public int $status = 200;

    public array $headers = [];

    public string $body = '';

    public array $chunks = [];

    public function status(int $status)
    {
        $this->status = $status;

        return $this;
    }

    public function addHeader(string $name, string $value)
    {
        $this->headers[strtolower($name)][] = $value;

        return $this;
    }

    public function send($body)
    {
        $this->body = (string) $body;

        return $this;
    }

    public function streamOutput(callable $producer)
    {
        ob_start(function (string $buffer) {
            if ($buffer !== '') {
                $this->chunks[] = $buffer;
            }

            return '';
        }, 1);

        $producer();

        ob_end_flush();

        return $this;
    }
}

<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Laravel\Octane\FrankenPhp\FrankenPhpClient;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FrankenPhpClientTest extends TestCase
{
    public function test_marshal_request()
    {
        $requestContext = new RequestContext();
        $marshaledRequest = (new FrankenPhpClient())->marshalRequest($requestContext);
        $this->assertInstanceOf(Request::class, $marshaledRequest[0]);
        $this->assertSame($requestContext, $marshaledRequest[1]);
    }

    public function test_response()
    {
        $response = \Mockery::mock(Response::class);
        $response->shouldReceive('send');

        (new FrankenPhpClient())->respond(new RequestContext(), new OctaneResponse($response));

        $this->assertTrue(true);
    }

    public function test_response_includes_the_output_buffer()
    {
        $response = new Response('Hello World');

        ob_start();

        (new FrankenPhpClient())->respond(
            new RequestContext(),
            new OctaneResponse($response, 'Output Buffer')
        );

        $this->assertSame('Output BufferHello World', ob_get_clean());
    }

    public function test_streamed_response_does_not_include_the_output_buffer()
    {
        $response = new StreamedResponse(static function () {
            echo 'Hello World';
        });

        ob_start();

        (new FrankenPhpClient())->respond(
            new RequestContext(),
            new OctaneResponse($response, 'Output Buffer')
        );

        $this->assertSame('Hello World', ob_get_clean());
    }

    public function test_binary_file_response_does_not_include_the_output_buffer()
    {
        $response = new BinaryFileResponse(__DIR__.'/public/foo.txt');

        ob_start();

        (new FrankenPhpClient())->respond(
            new RequestContext(),
            new OctaneResponse($response, 'Output Buffer')
        );

        $this->assertSame(file_get_contents(__DIR__.'/public/foo.txt'), ob_get_clean());
    }
}

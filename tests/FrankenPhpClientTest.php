<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Laravel\Octane\FrankenPhp\FrankenPhpClient;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
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

    public function test_response_with_streamed_generator()
    {
        $response = new StreamedResponse(function (): iterable {
            yield 'Hello ';
            yield 'World';
        }, 200);

        ob_start();

        (new FrankenPhpClient())->respond(new RequestContext(), new OctaneResponse($response));

        $this->assertSame('Hello World', ob_get_clean());
    }

    public function test_response_with_streamed_string_callback()
    {
        $response = new StreamedResponse(function (): string {
            return 'Hello World';
        }, 200);

        ob_start();

        (new FrankenPhpClient())->respond(new RequestContext(), new OctaneResponse($response));

        $this->assertSame('Hello World', ob_get_clean());
    }
}

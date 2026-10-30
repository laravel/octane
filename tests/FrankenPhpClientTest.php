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

    public function test_streamed_generator_response_flushes_each_yielded_chunk()
    {
        $chunks = [];

        ob_start(function (string $buffer) use (&$chunks) {
            if ($buffer !== '') {
                $chunks[] = $buffer;
            }

            return '';
        });

        (new FrankenPhpClient())->respond(new RequestContext(), new OctaneResponse(new StreamedResponse(function () {
            yield 'Hello';
            yield 'World';
        })));

        ob_end_clean();

        $this->assertSame(['Hello', 'World'], $chunks);
    }
}

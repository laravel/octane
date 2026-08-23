<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\FrankenPhp\FrankenPhpClient;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Testing\Fakes\FakeWorker;
use Mockery;
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
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('send');

        (new FrankenPhpClient())->respond(new RequestContext(), new OctaneResponse($response));

        $this->assertTrue(true);
    }

    public function test_response_with_a_streamed_generator_callback_echoes_each_yielded_chunk()
    {
        $response = new StreamedResponse(function () {
            yield 'Hello ';
            yield 'World';
        });

        ob_start();

        (new FrankenPhpClient())->respond(new RequestContext(), new OctaneResponse($response));

        $this->assertSame('Hello World', ob_get_clean());
    }

    public function test_response_with_a_regular_streamed_callback_is_left_to_symfony()
    {
        $response = Mockery::mock(StreamedResponse::class)->makePartial();
        $response->setCallback(function () {
            echo 'Hello World';
        });
        $response->shouldReceive('send')->once();

        (new FrankenPhpClient())->respond(new RequestContext(), new OctaneResponse($response));
    }

    public function test_worker_streams_a_generator_based_response_created_by_the_stream_response_helper()
    {
        $previousOctaneServerFlag = $_SERVER['LARAVEL_OCTANE'] ?? null;
        $_SERVER['LARAVEL_OCTANE'] = 1;

        try {
            $app = $this->createApplication();

            $app['router']->get('/stream', fn () => response()->stream(function () {
                yield "data: hello\n\n";
                yield "data: [DONE]\n\n";
            }, headers: ['Content-Type' => 'text/event-stream']));

            $appFactory = Mockery::mock(ApplicationFactory::class);
            $appFactory->shouldReceive('createApplication')->andReturn($app);

            $worker = new FakeWorker($appFactory, new class([Request::create('/stream')]) extends FrankenPhpClient
            {
                public function __construct(public array $requests)
                {
                }

                public function marshalRequest(RequestContext $context): array
                {
                    return [$context->request, $context];
                }
            });

            $worker->boot();

            ob_start();

            $worker->run();

            $this->assertSame("data: hello\n\ndata: [DONE]\n\n", ob_get_clean());
        } finally {
            if ($previousOctaneServerFlag === null) {
                unset($_SERVER['LARAVEL_OCTANE']);
            } else {
                $_SERVER['LARAVEL_OCTANE'] = $previousOctaneServerFlag;
            }
        }
    }
}

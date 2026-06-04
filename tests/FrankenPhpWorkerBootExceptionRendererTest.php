<?php

namespace Laravel\Octane\Tests;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Laravel\Octane\FrankenPhp\WorkerBootExceptionRenderer;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class FrankenPhpWorkerBootExceptionRendererTest extends TestCase
{
    protected ?Container $previousContainer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
    }

    public function test_worker_boot_exceptions_are_rendered_with_laravels_exception_handler()
    {
        $container = $this->useContainer();
        $exception = new RuntimeException('Worker failed to boot.');
        $response = new Response('Rendered by Laravel.', 500);

        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('render')
            ->once()
            ->with(Mockery::type(Request::class), $exception)
            ->andReturn($response);

        $container->instance(ExceptionHandler::class, $handler);

        $this->assertSame($response, (new WorkerBootExceptionRenderer($exception, false))->renderForRequest());
        $this->assertInstanceOf(Request::class, $container->make('request'));
    }

    public function test_worker_boot_exceptions_fall_back_to_a_plain_response()
    {
        $this->useContainer();

        $response = (new WorkerBootExceptionRenderer(new RuntimeException('Worker failed to boot.'), false))->renderForRequest();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Internal server error.', $response->getContent());
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
    }

    public function test_fallback_responses_include_exception_details_when_debugging()
    {
        $this->useContainer();

        $response = (new WorkerBootExceptionRenderer(new RuntimeException('Worker failed to boot.'), true))->renderForRequest();

        $this->assertStringContainsString('RuntimeException', $response->getContent());
        $this->assertStringContainsString('Worker failed to boot.', $response->getContent());
    }

    public function test_worker_boot_exceptions_fall_back_when_laravels_exception_handler_fails()
    {
        $container = $this->useContainer();
        $exception = new RuntimeException('Worker failed to boot.');

        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('render')
            ->once()
            ->andThrow(new RuntimeException('Handler failed.'));

        $container->instance(ExceptionHandler::class, $handler);

        $response = (new WorkerBootExceptionRenderer($exception, true))->renderForRequest();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('Worker failed to boot.', $response->getContent());
        $this->assertStringNotContainsString('Handler failed.', $response->getContent());
    }

    protected function useContainer(): Container
    {
        return tap(new Container(), fn ($container) => Container::setInstance($container));
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }
}

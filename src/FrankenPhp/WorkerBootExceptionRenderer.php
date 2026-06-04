<?php

namespace Laravel\Octane\FrankenPhp;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Laravel\Octane\Octane;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WorkerBootExceptionRenderer
{
    /**
     * Create a new worker boot exception renderer instance.
     */
    public function __construct(
        protected Throwable $exception,
        protected bool $debug,
    ) {
    }

    /**
     * Render the worker boot exception for the console.
     */
    public function renderForConsole(): void
    {
        try {
            $container = Container::getInstance();

            if ($container->bound(ExceptionHandler::class)) {
                $container->make(ExceptionHandler::class)
                    ->renderForConsole(new ConsoleOutput, $this->exception);

                return;
            }

            $this->writeExceptionToErrorLog($this->exception);
        } catch (Throwable) {
            $this->writeExceptionToErrorLog($this->exception);
        }
    }

    /**
     * Render the worker boot exception as an HTTP response.
     */
    public function renderForRequest(): Response
    {
        return $this->renderUsingLaravel() ?? $this->renderFallbackResponse();
    }

    /**
     * Render the exception using Laravel's exception handler when it is available.
     */
    protected function renderUsingLaravel(): ?Response
    {
        try {
            $container = Container::getInstance();

            if (! $container->bound(ExceptionHandler::class)) {
                return null;
            }

            $request = Request::capture();

            $container->instance('request', $request);

            return $container->make(ExceptionHandler::class)->render($request, $this->exception);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Render a fallback response when Laravel's exception handler is unavailable.
     */
    protected function renderFallbackResponse(): Response
    {
        return new Response(
            Octane::formatExceptionForClient($this->exception, $this->debug),
            500,
            [
                'Status' => '500 Internal Server Error',
                'Content-Type' => 'text/plain',
            ],
        );
    }

    /**
     * Write the exception details to the error log.
     */
    protected function writeExceptionToErrorLog(Throwable $exception): void
    {
        fwrite(STDERR, sprintf(
            "[octane bootstrap] %s: %s\n  in %s:%d\n%s\n",
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
        ));
    }
}

<?php

namespace Laravel\Octane;

use Closure;
use Illuminate\Http\Request;
use Laravel\Octane\Contracts\Client;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Whoops\Handler\PlainTextHandler;
use Whoops\Run;

class EmergencyWorker implements \Laravel\Octane\Contracts\Worker
{
    protected Run $whoops;

    public function __construct(
        protected Client $client,
        protected Throwable $exception,
    ) {
        $this->whoops = new Run();
        $this->whoops->allowQuit(false);
        $this->whoops->writeToOutput(false);
        $this->whoops->pushHandler(new PlainTextHandler());
    }

    public function handle(Request $request, RequestContext $context): void
    {
        $response = new Response();
        $response->setStatusCode(500);
        $response->headers->add(['Content-Type' => 'text/plain']);
        $response->setContent($this->whoops->handleException($this->exception));

        $this->client->respond($context, new OctaneResponse($response));
    }

    public function boot(): void
    {
    }

    public function handleTask($data)
    {
    }

    public function terminate(): void
    {
    }

    public function handleTick(): void
    {
    }

    public function onRequestHandled(Closure $callback)
    {
        return $this;
    }
}

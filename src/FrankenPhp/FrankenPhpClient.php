<?php

namespace Laravel\Octane\FrankenPhp;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Throwable;

class FrankenPhpClient implements Client
{
    /**
     * Marshal the given request context into an Illuminate request.
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            Request::createFromGlobals(),
            $context,
        ];
    }

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, OctaneResponse $octaneResponse): void
    {
        $octaneResponse->response->send();
    }

    /**
     * Send an error message to the server.
     */
    public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
    {
        error_log($e->getMessage(), 4);
    }
}

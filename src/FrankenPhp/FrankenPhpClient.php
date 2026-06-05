<?php

namespace Laravel\Octane\FrankenPhp;

use Generator;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Octane;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use ReflectionFunction;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FrankenPhpClient implements Client
{
    /**
     * Marshal the given request context into an Illuminate request.
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            Request::capture(),
            $context,
        ];
    }

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, OctaneResponse $octaneResponse): void
    {
        if (($octaneResponse->response instanceof StreamedResponse) &&
            ! is_null($responseCallback = static::resolveStreamResponseCallback($octaneResponse->response))) {
            $octaneResponse->response->sendHeaders();

            $response = $responseCallback();

            if (is_iterable($response)) {
                foreach ($response as $chunk) {
                    echo $chunk;
                    flush();
                }

                return;
            }

            if (is_string($response)) {
                echo $response;
                flush();
            }

            return;
        }

        $octaneResponse->response->send();
    }

    /**
     * Resolve the stream response callback from the given response.
     *
     * @param  \Symfony\Component\HttpFoundation\StreamedResponse  $response
     * @return \Closure|null
     */
    public static function resolveStreamResponseCallback(StreamedResponse $response)
    {
        if (is_null($responseCallback = $response->getCallback())) {
            return null;
        }

        $reflection = new ReflectionFunction($responseCallback);

        if ($reflection->isGenerator() || ($reflection->hasReturnType() === true &&
            in_array($reflection->getReturnType()?->getName(), [Generator::class, 'string']))) {
            return $responseCallback;
        }

        return null;
    }

    /**
     * Send an error message to the server.
     */
    public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
    {
        $response = new Response(
            Octane::formatExceptionForClient($e, $app->make('config')->get('app.debug')),
            500,
            [
                'Status' => '500 Internal Server Error',
                'Content-Type' => 'text/plain',
            ],
        );

        $response->send();
    }
}

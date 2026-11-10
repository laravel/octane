<?php

namespace Laravel\Octane\Pam;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Contracts\ServesStaticFiles;
use Laravel\Octane\MimeType;
use Laravel\Octane\Octane;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Laravel\Octane\StreamsGeneratorResponses;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PamClient implements Client, ServesStaticFiles
{
    use StreamsGeneratorResponses;

    /**
     * Marshal the given request context into an Illuminate request.
     */
    public function marshalRequest(RequestContext $context): array
    {
        $context['request'] = Request::capture();

        return [
            $context['request'],
            $context,
        ];
    }

    /**
     * Determine if the request can be served as a static file.
     */
    public function canServeRequestAsStaticFile(Request $request, RequestContext $context): bool
    {
        if (($context['octaneConfig']['serve_static_files'] ?? true) === false ||
            ! ($context['publicPath'] ?? false) ||
            ! in_array($request->getMethod(), ['GET', 'HEAD']) ||
            $request->path() === '/') {
            return false;
        }

        return ! is_null($this->staticFilePath($request, $context['publicPath']));
    }

    /**
     * Serve the static file that was requested.
     */
    public function serveStaticFile(Request $request, RequestContext $context): void
    {
        $path = $this->staticFilePath($request, $context['publicPath']);

        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => MimeType::get(pathinfo($path, PATHINFO_EXTENSION)),
        ], true, null, true, true);

        foreach ($context['octaneConfig']['static_file_headers'] ?? [] as $pattern => $headers) {
            if ($request->is($pattern)) {
                $response->headers->add($headers);
            }
        }

        $response->prepare($request);

        $response->isNotModified($request);

        $this->sendStreamedResponse($request, $context, $response);
    }

    /**
     * Get the path of the requested file if it may be served from the public directory.
     */
    protected function staticFilePath(Request $request, string $publicPath): ?string
    {
        $path = $request->decodedPath();

        if (str_contains($path, "\0") || in_array('..', explode('/', $path))) {
            return null;
        }

        $pathToFile = $publicPath.'/'.$path;

        return is_file($pathToFile) &&
            ! in_array(strtolower(pathinfo($pathToFile, PATHINFO_EXTENSION)), ['php', 'htaccess', 'config'])
                ? $pathToFile
                : null;
    }

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, OctaneResponse $octaneResponse): void
    {
        $response = $octaneResponse->response;

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            if ($response instanceof StreamedResponse) {
                $this->streamGeneratorResponse($response);
            }

            $this->sendStreamedResponse($context['request'] ?? null, $context, $response, $octaneResponse->outputBuffer);

            return;
        }

        if ($octaneResponse->outputBuffer) {
            $response->setContent($octaneResponse->outputBuffer.$response->getContent());
        }

        $this->sendResponse($response);
    }

    /**
     * Send the given response through the SAPI, which PAM captures.
     */
    protected function sendResponse(Response $response): void
    {
        // PAM reads the status code from the SAPI. PHP 8.5 warns when a status line was sent by
        // header('HTTP/...') earlier in the process, but the response code is still updated...
        @http_response_code($response->getStatusCode());

        foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            foreach ($values as $value) {
                header($name.': '.$value, strcasecmp($name, 'Content-Type') === 0);
            }
        }

        foreach ($response->headers->getCookies() as $cookie) {
            header('Set-Cookie: '.$cookie, false);
        }

        $response->sendContent();
    }

    /**
     * Stream the given response through the PAM response, chunk by chunk.
     */
    protected function sendStreamedResponse(?Request $request, RequestContext $context, Response $response, ?string $outputBuffer = null): void
    {
        $pamResponse = $context['pamResponse'];

        $pamResponse->status($response->getStatusCode());

        foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            if (in_array(strtolower($name), ['connection', 'transfer-encoding'])) {
                continue;
            }

            foreach ($values as $value) {
                $pamResponse->addHeader($name, $value);
            }
        }

        foreach ($response->headers->getCookies() as $cookie) {
            $pamResponse->addHeader('Set-Cookie', (string) $cookie);
        }

        if ($request?->isMethod('HEAD') || in_array($response->getStatusCode(), [204, 304])) {
            $pamResponse->send('');

            return;
        }

        $pamResponse->streamOutput(function () use ($response, $outputBuffer) {
            echo $outputBuffer;

            $response->sendContent();
        });
    }

    /**
     * Send an error message to the server.
     */
    public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
    {
        $response = new Response(
            Octane::formatExceptionForClient($e, $app->make('config')->get('app.debug')),
            500,
            ['Content-Type' => 'text/plain'],
        );

        $this->sendResponse($response);
    }
}

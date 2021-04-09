<?php

namespace Laravel\Octane\Swoole;

use DateTime;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Contracts\ServesStaticFiles;
use Laravel\Octane\MimeType;
use Laravel\Octane\Octane;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SwooleClient implements Client, ServesStaticFiles
{
    public function __construct(protected int $chunkSize = 1048576)
    {
    }

    /**
     * Marshal the given request context into an Illuminate request.
     *
     * @param  \Laravel\Octane\RequestContext  $context
     * @return array
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            (new Actions\ConvertSwooleRequestToIlluminateRequest)(
                $context->swooleRequest,
                PHP_SAPI
            ),
            $context,
        ];
    }

    /**
     * Detemrine if the request can be served as a static file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Laravel\Octane\RequestContext  $context
     * @return bool
     */
    public function canServeRequestAsStaticFile(Request $request, RequestContext $context): bool
    {
        if (! ($context->publicPath ?? false) ||
            $request->path() === '/') {
            return false;
        }

        $publicPath = $context->publicPath;

        return $this->fileIsServable(
            $publicPath,
            realpath($publicPath.'/'.$request->path()),
        );
    }

    /**
     * Determine if the given file is servable.
     *
     * @param  string  $publicPath
     * @param  string  $pathToFile
     * @return bool
     */
    protected function fileIsServable(string $publicPath, string $pathToFile): bool
    {
        return $pathToFile &&
               ! in_array(pathinfo($pathToFile, PATHINFO_EXTENSION), ['php', 'htaccess', 'config']) &&
               str_starts_with($pathToFile, $publicPath) &&
               is_file($pathToFile) && filesize($pathToFile);
    }

    /**
     * Serve the static file that was requested.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Laravel\Octane\RequestContext  $context
     * @return void
     */
    public function serveStaticFile(Request $request, RequestContext $context): void
    {
        $swooleResponse = $context->swooleResponse;

        $publicPath = $context->publicPath;

        $swooleResponse->status(200);
        $swooleResponse->header('Content-Type', MimeType::get(pathinfo($request->path(), PATHINFO_EXTENSION)));
        $swooleResponse->sendfile(realpath($publicPath.'/'.$request->path()));
    }

    /**
     * Send the response to the server.
     *
     * @param  \Laravel\Octane\RequestContext  $context
     * @param  \Laravel\Octane\OctaneResponse  $octaneResponse
     * @return void
     */
    public function respond(RequestContext $context, OctaneResponse $octaneResponse): void
    {
        $this->sendResponseHeaders($octaneResponse->response, $context->swooleResponse);
        $this->sendResponseContent($octaneResponse, $context->swooleResponse);
    }

    /**
     * Send the headers from the Illuminate response to the Swoole response.
     *
     * @param  \Symfony\Component\HtpFoundation\Response  $response
     * @param  \Swoole\Http\Response  $response
     * @return void
     */
    public function sendResponseHeaders(Response $response, SwooleResponse $swooleResponse): void
    {
        if (! $response->headers->has('Date')) {
            $response->setDate(DateTime::createFromFormat('U', time()));
        }

        $headers = $response->headers->allPreserveCase();

        if (isset($headers['Set-Cookie'])) {
            unset($headers['Set-Cookie']);
        }

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $swooleResponse->header($name, $value);
            }
        }

        $swooleResponse->status($response->getStatusCode());

        foreach ($response->headers->getCookies() as $cookie) {
            $swooleResponse->{$cookie->isRaw() ? 'rawcookie' : 'cookie'}(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }
    }

    /**
     * Send the headers from the Illuminate response to the Swoole response.
     *
     * @param  \Laravel\Octane\OctaneResponse  $response
     * @param  \Swoole\Http\Response  $response
     * @return void
     */
    protected function sendResponseContent(OctaneResponse $octaneResponse, SwooleResponse $swooleResponse): void
    {
        if ($octaneResponse->response instanceof BinaryFileResponse) {
            $swooleResponse->sendfile($octaneResponse->response->getFile()->getPathname());

            return;
        }

        if ($octaneResponse->outputBuffer) {
            $swooleResponse->write($octaneResponse->outputBuffer);
        }

        if ($octaneResponse->response instanceof StreamedResponse) {
            ob_start(function ($data) use ($swooleResponse) {
                logger('write '.strlen($data));
                if (strlen($data) > 0) {
                    $swooleResponse->write($data);
                }

                return '';
            }, 1);

            $octaneResponse->response->sendContent();

            ob_end_clean();

            $swooleResponse->end();

            return;
        }

        $content = $octaneResponse->response->getContent();

        $length = strlen($content);

        if ($length <= $this->chunkSize) {
            $swooleResponse->end($content);

            return;
        }

        for ($offset = 0; $offset < $length; $offset += $this->chunkSize) {
            $swooleResponse->write(substr($content, $offset, $this->chunkSize));
        }

        $swooleResponse->end();
    }

    /**
     * Send an error message to the server.
     *
     * @param  \Throwable  $e
     * @param  \Illuminate\Foundation\Application  $app
     * @param  \Illuminate\Http\Request  $request
     * @param  \Laravel\Octane\RequestContext  $context
     * @return void
     */
    public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
    {
        $context->swooleResponse->header('Status', '500 Internal Server Error');
        $context->swooleResponse->header('Content-Type', 'text/plain');

        $context->swooleResponse->end(
            Octane::formatExceptionForClient($e, $app->make('config')->get('app.debug'))
        );
    }
}

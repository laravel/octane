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
    const STATUS_CODE_REASONS = [
        419 => 'Page Expired',
        431 => 'Request Header Fields Too Large',                             // RFC6585
        451 => 'Unavailable For Legal Reasons',                               // RFC7725
    ];

    public function __construct(protected int $chunkSize = 1048576)
    {
    }

    /**
     * Marshal the given request context into an Illuminate request.
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
     * Determine if the request can be served as a static file.
     */
    public function canServeRequestAsStaticFile(Request $request, RequestContext $context): bool
    {
        if (! ($context->publicPath ?? false) ||
            $request->path() === '/') {
            return false;
        }

        $publicPath = $context->publicPath;

        $pathToFile = realpath($publicPath.'/'.$request->path());

        if ($this->isValidFileWithinSymlink($request, $publicPath, $pathToFile)) {
            $pathToFile = $publicPath.'/'.$request->path();
        }

        return $this->fileIsServable(
            $publicPath,
            $pathToFile,
        );
    }

    /**
     * Determine if the request is for a valid static file within a symlink.
     */
    private function isValidFileWithinSymlink(Request $request, string $publicPath, string $pathToFile): bool
    {
        $pathAfterSymlink = $this->pathAfterSymlink($publicPath, $request->path());

        return $pathAfterSymlink && str_ends_with($pathToFile, $pathAfterSymlink);
    }

    /**
     * If the given public file is within a symlinked directory, return the path after the symlink.
     *
     * @return string|bool
     */
    private function pathAfterSymlink(string $publicPath, string $path)
    {
        $directories = explode('/', $path);

        while ($directory = array_shift($directories)) {
            $publicPath .= '/'.$directory;

            if (is_link($publicPath)) {
                return implode('/', $directories);
            }
        }

        return false;
    }

    /**
     * Determine if the given file is servable.
     */
    protected function fileIsServable(string $publicPath, string $pathToFile): bool
    {
        return $pathToFile &&
               ! in_array(pathinfo($pathToFile, PATHINFO_EXTENSION), ['php', 'htaccess', 'config']) &&
               str_starts_with($pathToFile, $publicPath) &&
               is_file($pathToFile);
    }

    /**
     * Serve the static file that was requested.
     */
    public function serveStaticFile(Request $request, RequestContext $context): void
    {
        $swooleResponse = $context->swooleResponse;

        $publicPath = $context->publicPath;
        $octaneConfig = $context->octaneConfig ?? [];

        if (! empty($octaneConfig['static_file_headers'])) {
            $formatHeaders = config('octane.swoole.format_headers', true);

            foreach ($octaneConfig['static_file_headers'] as $pattern => $headers) {
                if ($request->is($pattern)) {
                    foreach ($headers as $name => $value) {
                        $swooleResponse->header($name, $value, $formatHeaders);
                    }
                }
            }
        }

        $swooleResponse->status(200);
        $swooleResponse->header('Content-Type', MimeType::get(pathinfo($request->path(), PATHINFO_EXTENSION)));
        $swooleResponse->sendfile(realpath($publicPath.'/'.$request->path()));
    }

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, OctaneResponse $octaneResponse): void
    {
        $this->sendResponseHeaders($octaneResponse->response, $context->swooleResponse);
        $this->sendResponseContent($octaneResponse, $context->swooleResponse);
    }

    /**
     * Send the headers from the Illuminate response to the Swoole response.
     *
     * @param  \Swoole\Http\Response  $response
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

        $formatHeaders = config('octane.swoole.format_headers', true);

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $swooleResponse->header($name, $value, $formatHeaders);
            }
        }

        if (! is_null($reason = $this->getReasonFromStatusCode($response->getStatusCode()))) {
            $swooleResponse->status($response->getStatusCode(), $reason);
        } else {
            $swooleResponse->status($response->getStatusCode());
        }

        foreach ($response->headers->getCookies() as $cookie) {
            $shouldDelete = (string) $cookie->getValue() === '';

            $swooleResponse->{$cookie->isRaw() ? 'rawcookie' : 'cookie'}(
                $cookie->getName(),
                $shouldDelete ? 'deleted' : $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain() ?? '',
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
                $cookie->getSameSite() ?? '',
            );
        }
    }

    /**
     * Send the content from the Illuminate response to the Swoole response.
     *
     * @param  \Laravel\Octane\OctaneResponse  $response
     * @param  \Swoole\Http\Response  $response
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

        if (($length = strlen($content)) === 0) {
            $swooleResponse->end();

            return;
        }

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
     */
    public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
    {
        $context->swooleResponse->header('Status', '500 Internal Server Error');
        $context->swooleResponse->header('Content-Type', 'text/plain');

        $context->swooleResponse->end(
            Octane::formatExceptionForClient($e, $app->make('config')->get('app.debug'))
        );
    }

    /**
     * Get the HTTP reason clause for non-standard status codes.
     */
    protected function getReasonFromStatusCode(int $code): ?string
    {
        if (array_key_exists($code, self::STATUS_CODE_REASONS)) {
            return self::STATUS_CODE_REASONS[$code];
        }

        return null;
    }
}

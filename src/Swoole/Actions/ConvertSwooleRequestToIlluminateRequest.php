<?php

namespace Laravel\Octane\Swoole\Actions;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class ConvertSwooleRequestToIlluminateRequest
{
    /**
     * Convert the given Swoole request into an Illuminate request.
     *
     * @param  \Swoole\Http\Request  $swooleRequest
     * @param  array<int, string>  $trustedProxies
     */
    public function __invoke($swooleRequest, string $phpSapi, array $trustedProxies = ['127.0.0.1', '::1']): Request
    {
        $serverVariables = $this->prepareServerVariables(
            $swooleRequest->server ?? [],
            $swooleRequest->header ?? [],
            $phpSapi,
            $trustedProxies
        );

        $request = new SymfonyRequest(
            $swooleRequest->get ?? [],
            $swooleRequest->post ?? [],
            [],
            $swooleRequest->cookie ?? [],
            $swooleRequest->files ?? [],
            $serverVariables,
            $swooleRequest->rawContent(),
        );

        if (str_starts_with((string) $request->headers->get('CONTENT_TYPE'), 'application/x-www-form-urlencoded') &&
            in_array(strtoupper($request->server->get('REQUEST_METHOD', 'GET')), ['PUT', 'PATCH', 'DELETE'])) {
            parse_str($request->getContent(), $data);

            $request->request->replace($data);
        }

        return Request::createFromBase($request);
    }

    /**
     * Parse the "server" variables and headers into a single array of $_SERVER variables.
     */
    protected function prepareServerVariables(array $server, array $headers, string $phpSapi, array $trustedProxies = []): array
    {
        $results = [];

        foreach ($server as $key => $value) {
            $results[strtoupper($key)] = $value;
        }

        $results = array_merge(
            $results,
            $this->formatHttpHeadersIntoServerVariables(
                $headers,
                isset($results['REMOTE_ADDR']) && IpUtils::checkIp((string) $results['REMOTE_ADDR'], $trustedProxies)
            )
        );

        if (isset($results['REQUEST_URI'], $results['QUERY_STRING']) &&
            strlen($results['QUERY_STRING']) > 0 &&
            strpos($results['REQUEST_URI'], '?') === false) {
            $results['REQUEST_URI'] .= '?'.$results['QUERY_STRING'];
        }

        return $phpSapi === 'cli-server'
                ? $this->correctHeadersSetIncorrectlyByPhpDevServer($results)
                : $results;
    }

    /**
     * Format the given HTTP headers into properly formatted $_SERVER variables.
     *
     * A trusted proxy may set REMOTE_ADDR and SERVER_PORT using "REMOTE_ADDR" and "SERVER_PORT" headers.
     */
    protected function formatHttpHeadersIntoServerVariables(array $headers, bool $fromTrustedProxy = false): array
    {
        $results = [];

        foreach ($headers as $key => $value) {
            if ($fromTrustedProxy && in_array($key, ['remote_addr', 'server_port'], true)) {
                $results[strtoupper($key)] = $value;

                continue;
            }

            $results['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $results;
    }

    /**
     * Correct headers set incorrectly by built-in PHP development server.
     */
    protected function correctHeadersSetIncorrectlyByPhpDevServer(array $headers): array
    {
        if (array_key_exists('HTTP_CONTENT_LENGTH', $headers)) {
            $headers['CONTENT_LENGTH'] = $headers['HTTP_CONTENT_LENGTH'];
        }

        if (array_key_exists('HTTP_CONTENT_TYPE', $headers)) {
            $headers['CONTENT_TYPE'] = $headers['HTTP_CONTENT_TYPE'];
        }

        return $headers;
    }
}

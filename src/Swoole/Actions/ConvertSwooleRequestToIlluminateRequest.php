<?php

namespace Laravel\Octane\Swoole\Actions;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class ConvertSwooleRequestToIlluminateRequest
{
    /**
     * Convert the given Swoole request into an Illuminate request.
     *
     * @param  \Swoole\Http\Request  $swooleRequest
     * @param  string  $phpSapi
     * @return \Illuminate\Http\Request
     */
    public function __invoke($swooleRequest, string $phpSapi): Request
    {
        $serverVariables = $this->prepareServerVariables(
            $swooleRequest->server ?? [],
            $swooleRequest->header ?? [],
            $phpSapi
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

            $request->request = new ParameterBag($data);
        }

        return Request::createFromBase($request);
    }

    /**
     * Parse the "server" variables and headers into a single array of $_SERVER variables.
     *
     * @param  array  $server
     * @param  array  $headers
     * @param  string  $phpSapi
     * @return array
     */
    protected function prepareServerVariables(array $server, array $headers, string $phpSapi): array
    {
        $results = [];

        foreach ($server as $key => $value) {
            $results[strtoupper($key)] = $value;
        }

        $results = array_merge(
            $results,
            $this->formatHttpHeadersIntoServerVariables($headers)
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
     * @param  array  $headers
     * @return array
     */
    protected function formatHttpHeadersIntoServerVariables(array $headers): array
    {
        $results = [];

        foreach ($headers as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));

            if (! in_array($key, ['HTTPS', 'REMOTE_ADDR', 'SERVER_PORT'])) {
                $key = 'HTTP_'.$key;
            }

            $results[$key] = $value;
        }

        return $results;
    }

    /**
     * Correct headers set incorrectly by built-in PHP development server.
     *
     * @param  array  $headers
     * @return array
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

<?php

namespace Laravel\Octane;

use Throwable;

class Stream
{
    /**
     * Stream the given throwable to stderr.
     *
     * @param  \Throwable  $throwable
     * @return void
     */
    public static function throwable(Throwable $throwable)
    {
        fwrite(STDERR, json_encode([
            'class' => get_class($throwable),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'message' => $throwable->getMessage(),
            'trace' => array_slice($throwable->getTrace(), 0, 2),
        ])."\n");
    }

    /**
     * Stream the given request information to stdout.
     *
     * @param  string  $method
     * @param  string  $url
     * @param  int  $statusCode
     * @param  float  $duration
     * @return void
     */
    public static function request(string $method, string $url, int $statusCode, float $duration)
    {
        fwrite(STDOUT, json_encode([
            'method' => $method,
            'url' => $url,
            'statusCode' => $statusCode,
            'duration' => $duration,
        ])."\n");
    }
}

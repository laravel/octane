<?php

namespace Laravel\Octane;

class Stream
{
    /**
     * Streams the given error message.
     *
     * @param  string $error
     * @return void
     */
    public static function error($message)
    {
        fwrite(STDERR, (string) str_replace("\n", ' ', $string) . "\n");
    }

    /**
     * Streams the given request information.
     *
     * @param  string $method
     * @param  string $url
     * @param  int $statusCode
     * @param  float $duration
     * @return void
     */
    public static function request($method, $url, $statusCode, $duration)
    {
        fwrite(STDOUT, json_encode([
            'method' => $method,
            'url' => $url,
            'statusCode' => $statusCode,
            'duration' => $duration,
        ]). "\n");
    }
}

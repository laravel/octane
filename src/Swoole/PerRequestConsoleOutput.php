<?php

namespace Laravel\Octane\Swoole;

use Laravel\Octane\ConsoleColor;

class PerRequestConsoleOutput
{
    /**
     * Write request information to the console.
     *
     * @param  string  $destination
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  float  $lastRequestTime
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public static function write($destination, $request, $response, $lastRequestTime, $sandbox)
    {
        $statusCode = $response->getStatusCode();

        fwrite($destination, sprintf(
            "%s %s %s %s\n",
            match (true) {
                $statusCode >= 500 => ConsoleColor::set($statusCode, 'red'),
                $statusCode >= 400 => ConsoleColor::set($statusCode, 'yellow'),
                $statusCode >= 300 => ConsoleColor::set($statusCode, 'cyan'),
                $statusCode >= 100 => ConsoleColor::set($statusCode, 'green'),
            },
            ConsoleColor::set('('.round((microtime(true) - $lastRequestTime) * 1000, 2).'ms)', 'magenta+bold'),
            ConsoleColor::set($request->getMethod(), 'cyan'),
            $request->fullUrl(),
        ));
    }
}

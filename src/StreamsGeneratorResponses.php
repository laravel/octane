<?php

namespace Laravel\Octane;

use Closure;
use ReflectionFunction;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait StreamsGeneratorResponses
{
    /**
     * Write each chunk yielded by a generator stream callback, stopping when a write returns false.
     */
    protected function streamGeneratorResponse(StreamedResponse $response, ?Closure $write = null): void
    {
        $callback = $response->getCallback();

        if (is_null($callback) || ! (new ReflectionFunction($callback))->isGenerator()) {
            return;
        }

        $write ??= $this->writeStreamedChunk(...);

        $response->setCallback(function () use ($callback, $write) {
            foreach ($callback() as $chunk) {
                if ($write($chunk) === false) {
                    break;
                }
            }
        });
    }

    /**
     * Echo and flush the given chunk, returning false once the client has disconnected.
     */
    protected function writeStreamedChunk(string $chunk): bool
    {
        echo $chunk;

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();

        return ! connection_aborted();
    }
}

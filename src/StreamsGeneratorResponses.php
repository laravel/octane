<?php

namespace Laravel\Octane;

use ReflectionFunction;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait StreamsGeneratorResponses
{
    /**
     * Write and flush each chunk yielded by a generator stream callback, which Symfony would otherwise discard.
     */
    protected function streamGeneratorResponse(StreamedResponse $response): void
    {
        $callback = $response->getCallback();

        if (is_null($callback) || ! (new ReflectionFunction($callback))->isGenerator()) {
            return;
        }

        $response->setCallback(function () use ($callback) {
            foreach ($callback() as $chunk) {
                echo $chunk;

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        });
    }
}

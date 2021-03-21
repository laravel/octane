<?php

namespace Laravel\Octane;

use Laravel\Octane\Contracts\ConcurrentOperationDispatcher;
use Throwable;

class Octane
{
    use ProvidesDefaultConfigurationOptions;
    use ProvidesRouting;

    /**
     * Get the concurrent operation manager.
     *
     * @return \Laravel\Contracts\Octane\ConcurrentOperationDispatcher
     */
    public function concurrently()
    {
        return app(ConcurrentOperationDispatcher::class);
    }

    /**
     * Format an exception to a string that should be returned to the client.
     *
     * @param  \Throwable  $e
     * @param  bool  $debug
     * @return string
     */
    public static function formatExceptionForClient(Throwable $e, $debug = false): string
    {
        return $debug ? (string) $e : 'Internal server error.';
    }
}

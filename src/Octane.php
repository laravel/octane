<?php

namespace Laravel\Octane;

use Throwable;

class Octane
{
    use Concerns\ProvidesConcurrencySupport;
    use Concerns\ProvidesDefaultConfigurationOptions;
    use Concerns\ProvidesRouting;

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

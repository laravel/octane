<?php

namespace Laravel\Octane\Exceptions;

use Exception;

class StaleApplicationException extends Exception
{
    /**
     * Create a new stale application exception.
     *
     * @param  array<int, string>  $changedFiles
     */
    public function __construct(public array $changedFiles = [])
    {
        $files = implode(PHP_EOL, array_map(
            fn (string $file) => '  - '.$file,
            $changedFiles
        ));

        parent::__construct(
            "Application files have changed since this Octane worker loaded them. The worker is serving stale PHP classes and will reload. Retry the request.\n".
            ($files !== '' ? "Changed files:\n".$files : '')
        );
    }
}

<?php

namespace Laravel\Octane\FrankenPhp\Concerns;

use Symfony\Component\Process\ExecutableFinder;

trait FindsFrankenPhpBinary
{
    /**
     * Find the FrankenPHP binary used by the application.
     */
    protected function findFrankenPhpBinary(): ?string
    {
        return (new ExecutableFinder())->find('frankenphp', null, [base_path()]);
    }
}

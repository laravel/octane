<?php

namespace Laravel\Octane\FrankenPhp\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;

trait FindsFrankenPhpBinary
{
    /**
     * Find the FrankenPHP binary used by the application.
     */
    protected function findFrankenPhpBinary(): ?string
    {
        if (file_exists($basePath = base_path('frankenphp'))) {
            return $basePath;
        }

        if (! is_null($frankenPhpBinary = (new ExecutableFinder)->find('frankenphp', null, [base_path()]))) {
            if (! Str::contains($frankenPhpBinary, 'vendor/bin/frankenphp')) {
                return $frankenPhpBinary;
            }
        }

        return null;
    }
}

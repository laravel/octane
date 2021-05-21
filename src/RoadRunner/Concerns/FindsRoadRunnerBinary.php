<?php

namespace Laravel\Octane\RoadRunner\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;

trait FindsRoadRunnerBinary
{
    /**
     * Find the RoadRunner binary used by the application.
     *
     * @return string
     */
    protected function findRoadRunnerBinary(): ?string
    {
        if (file_exists(base_path('rr'))) {
            return base_path('rr');
        }

        if (! is_null($roadRunnerBinary = (new ExecutableFinder)->find('rr', null, [base_path()]))) {
            if (! Str::contains($roadRunnerBinary, 'vendor/bin/rr')) {
                return $roadRunnerBinary;
            }
        }

        return null;
    }
}

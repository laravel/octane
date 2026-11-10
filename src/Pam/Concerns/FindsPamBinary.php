<?php

namespace Laravel\Octane\Pam\Concerns;

use Symfony\Component\Process\ExecutableFinder;

trait FindsPamBinary
{
    /**
     * Find the PAM binary used by the application.
     */
    protected function findPamBinary(): ?string
    {
        $home = $_SERVER['HOME'] ?? $_ENV['HOME'] ?? getenv('HOME');

        return (new ExecutableFinder)->find('pam', null, array_filter([
            base_path(),
            $home ? $home.'/.local/bin' : null,
        ]));
    }
}

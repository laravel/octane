<?php

namespace Laravel\Octane;

use Symfony\Component\Process\Process;

class SymfonyProcessFactory
{
    /**
     * Create a new Symfony process instance.
     *
     * @param  array  $command
     * @param  string  $cwd
     * @param  array  $env
     * @param  mixed|null  $input
     * @param  float|null  $timeout
     * @return \Symfony\Component\Process\Process
     */
    public function createProcess(array $command, string $cwd = null, array $env = null, $input = null, ?float $timeout = 60)
    {
        return new Process($command, $cwd, $env, $input, $timeout);
    }
}

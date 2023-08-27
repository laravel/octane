<?php

namespace Laravel\Octane\Contracts;

interface ServerProcessInspector
{
    /**
     * Determine if the server process is running.
     */
    public function serverIsRunning(): bool;

    /**
     * Reload the workers.
     */
    public function reloadServer(): void;

    /**
     * Stop the server.
     */
    public function stopServer(): bool;
}

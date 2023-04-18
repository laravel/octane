<?php

namespace Laravel\Octane\Contracts;

interface ServerProcessInspector
{
    /**
     * Determine if the server process is running.
     *
     * @return bool
     */
    public function serverIsRunning(): bool;

    /**
     * Reload the workers.
     *
     * @return void
     */
    public function reloadServer(): void;

    /**
     * Stop the server.
     *
     * @return bool
     */
    public function stopServer(): bool;
}

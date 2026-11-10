<?php

namespace Laravel\Octane\Pam;

use Laravel\Octane\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Laravel\Octane\PosixExtension;

class ServerProcessInspector implements ServerProcessInspectorContract
{
    /**
     * Create a new server process inspector instance.
     */
    public function __construct(
        protected ServerStateFile $serverStateFile,
        protected PosixExtension $posix,
    ) {}

    /**
     * Determine if the PAM server process is running.
     */
    public function serverIsRunning(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return $masterProcessId && $this->posix->kill($masterProcessId, 0);
    }

    /**
     * Reload the PAM workers.
     */
    public function reloadServer(): void
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        $this->posix->kill($masterProcessId, SIGHUP);
    }

    /**
     * Stop the PAM server.
     */
    public function stopServer(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return (bool) $this->posix->kill($masterProcessId, SIGTERM);
    }
}

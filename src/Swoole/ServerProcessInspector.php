<?php

namespace Laravel\Octane\Swoole;

use Swoole\Process;

class ServerProcessInspector
{
    public function __construct(
        protected SignalDispatcher $dispatcher,
        protected ServerStateFile $serverStateFile
    ) {
    }

    /**
     * Determine if the Swoole server process is running.
     *
     * @return bool
     */
    public function serverIsRunning(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
            'managerProcessId' => $managerProcessId
        ] = $this->serverStateFile->read();

        return $managerProcessId
                ? $masterProcessId && $managerProcessId && $this->dispatcher->canCommunicateWith((int) $managerProcessId)
                : $masterProcessId && $this->dispatcher->canCommunicateWith((int) $masterProcessId);
    }

    /**
     * Reload the Swoole workers.
     *
     * @return void
     */
    public function reloadServer(): void
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        $this->dispatcher->signal($masterProcessId, SIGUSR1);
    }

    /**
     * Stop the Swoole server.
     *
     * @return bool
     */
    public function stopServer(): bool
    {
        [
            'masterProcessId' => $masterProcessId
        ] = $this->serverStateFile->read();

        return $this->dispatcher->terminate($masterProcessId, 15);
    }
}

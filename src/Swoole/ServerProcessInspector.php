<?php

namespace Laravel\Octane\Swoole;

use Laravel\Octane\Exec;

class ServerProcessInspector
{
    public function __construct(
        protected SignalDispatcher $dispatcher,
        protected ServerStateFile $serverStateFile,
        protected Exec $exec,
        protected int $terminateWait,
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

        $this->dispatcher->signal((int) $masterProcessId, SIGUSR1);
    }

    /**
     * Stop the Swoole server.
     *
     * @return bool
     */
    public function stopServer(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
            'managerProcessId' => $managerProcessId
        ] = $this->serverStateFile->read();

        $workerProcessIds = $this->exec->run('pgrep -P '.$managerProcessId);

        $this->dispatcher->terminate(array_map('intval', $workerProcessIds), $this->terminateWait);
        $this->dispatcher->terminate([(int) $managerProcessId, (int) $masterProcessId]);

        return true;
    }
}

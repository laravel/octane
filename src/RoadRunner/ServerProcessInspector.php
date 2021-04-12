<?php

namespace Laravel\Octane\RoadRunner;

use Laravel\Octane\PosixExtension;
use Laravel\Octane\SymfonyProcessFactory;
use Symfony\Component\Process\Process;

class ServerProcessInspector
{
    public function __construct(
        protected ServerStateFile $serverStateFile,
        protected SymfonyProcessFactory $processFactory,
        protected PosixExtension $posix
    ) {
    }

    /**
     * Determine if the RoadRunner server process is running.
     *
     * @return bool
     */
    public function serverIsRunning(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return $masterProcessId && $this->posix->kill($masterProcessId, 0);
    }

    /**
     * Reload the RoadRunner workers.
     *
     * @return void
     */
    public function reloadServer(): void
    {
        $this->processFactory->createProcess([
            './rr', 'reset',
        ], base_path(), null, null, null)->start(function ($type, $buffer) {
            if ($type === Process::ERR) {
                throw new \RuntimeException('Cannot reload RoadRunner: '.$buffer);
            }
        });
    }

    /**
     * Stop the RoadRunner server.
     *
     * @return bool
     */
    public function stopServer(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return (bool) $this->posix->kill($masterProcessId, SIGTERM);
    }
}

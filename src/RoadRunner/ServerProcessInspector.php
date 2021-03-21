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
     * @param  string  $basePath
     * @return void
     */
    public function reloadServer(string $basePath): void
    {
        $this->processFactory->createProcess([
            './rr', 'http:reset',
        ], $basePath, null, null, null)->run();
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

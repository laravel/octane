<?php

namespace Laravel\Octane\FileWatchers;

use Laravel\Octane\Contracts\FileWatcher;
use Laravel\Octane\Exceptions\FileWatcherException;
use Symfony\Component\Process\Process;

class ChokidarFileWatcher implements FileWatcher
{
    protected Process $process;

    public function __construct(string $executable, array $paths)
    {
        $this->process = tap(new Process([
            $executable, 'file-watcher.js', json_encode($paths),
        ], realpath(__DIR__.'/../../bin'), null, null, null))->start();
    }

    /**
     * Determine if any file changes occurred.
     *
     * @return bool
     * @throws FileWatcherException
     */
    public function hasChanges(): bool
    {
        if ($this->process->isTerminated()) {
            throw new FileWatcherException(
                'Watcher process has terminated. Please ensure chokidar is installed.'.PHP_EOL.
                $this->process->getErrorOutput()
            );
        }

        return ! empty($this->process->getIncrementalOutput());
    }
}

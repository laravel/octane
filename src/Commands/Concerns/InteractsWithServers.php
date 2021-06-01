<?php

namespace Laravel\Octane\Commands\Concerns;

use InvalidArgumentException;
use Laravel\Octane\Contracts\FileWatcher;
use Laravel\Octane\Exceptions\FileWatcherException;
use Laravel\Octane\Exceptions\ServerShutdownException;
use Laravel\Octane\FileWatchers\ChokidarFileWatcher;
use Symfony\Component\Process\ExecutableFinder;

trait InteractsWithServers
{
    /**
     * Run the given server process.
     *
     * @param  \Symfony\Component\Process\Process  $server
     * @param  \Laravel\Octane\Swoole\ServerProcessInspector|\Laravel\Octane\RoadRunner\ServerProcessInspector  $inspector
     * @param  string  $type
     * @return int
     */
    protected function runServer($server, $inspector, $type)
    {
        while (! $server->isStarted()) {
            sleep(1);
        }

        $this->writeServerRunningMessage();

        try {
            $watcher = $this->createFileWatcher();

            while ($server->isRunning()) {
                $this->writeServerOutput($server);

                if ($watcher->hasChanges()) {
                    $this->info('Application change detected. Restarting workers…');

                    $inspector->reloadServer();
                }

                usleep(500 * 1000);
            }

            $this->writeServerOutput($server);
        } catch (FileWatcherException $e) {
            $this->error($e->getMessage());

            return 1;
        } catch (ServerShutdownException) {
            return 1;
        } finally {
            $this->stopServer();
        }

        return $server->getExitCode();
    }

    /**
     * Create the file watcher instance for the server.
     *
     * @return FileWatcher
     * @throws FileWatcherException
     */
    protected function createFileWatcher(): FileWatcher
    {
        if (! $this->option('watch')) {
            return new class implements FileWatcher
            {
                public function hasChanges(): bool
                {
                    return false;
                }
            };
        }

        if (empty($paths = config('octane.watch'))) {
            throw new InvalidArgumentException(
                'List of directories/files to watch not found. Please update your "config/octane.php" configuration file.',
            );
        }

        $paths = collect($paths)
            ->map(fn ($path) => base_path($path))
            ->all();

        if ($executable = (new ExecutableFinder)->find('node')) {
            return new ChokidarFileWatcher($executable, $paths);
        }

        // TODO: Add support for inotify

        throw new FileWatcherException(
            'File watcher could not be initialized.'.PHP_EOL.
            'Please make sure you have enabled support for at least of one the available file watchers: '.
            'https://laravel.com/docs/8.x/octane#watching-for-file-changes'
        );
    }

    /**
     * Write the server start "message" to the console.
     *
     * @return void
     */
    protected function writeServerRunningMessage()
    {
        $this->info('Server running…');

        $this->output->writeln([
            '',
            '  Local: <fg=white;options=bold>http://'.$this->option('host').':'.$this->option('port').' </>',
            '',
            '  <fg=yellow>Press Ctrl+C to stop the server</>',
            '',
        ]);
    }

    /**
     * Returns the list of signals to subscribe.
     *
     * @return array
     */
    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    /**
     * The method will be called when the application is signaled.
     *
     * @param  int  $signal
     * @return void
     */
    public function handleSignal(int $signal): void
    {
        $this->stopServer();
    }
}

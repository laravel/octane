<?php

namespace Laravel\Octane\Commands\Concerns;

use InvalidArgumentException;
use Laravel\Octane\Exceptions\ServerShutdownException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

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

        $watcher = $this->startServerWatcher();

        try {
            while ($server->isRunning()) {
                $this->writeServerOutput($server);

                if ($watcher->isRunning() &&
                    $watcher->getIncrementalOutput()) {
                    $this->info('Application change detected. Restarting workers…');

                    $inspector->reloadServer();
                } elseif ($watcher->isTerminated()) {
                    $this->error(
                        'Watcher process has terminated. Please ensure Node and chokidar are installed.'.PHP_EOL.
                        $watcher->getErrorOutput()
                    );

                    return 1;
                }

                usleep(500 * 1000);
            }

            $this->writeServerOutput($server);
        } catch (ServerShutdownException) {
            return 1;
        } finally {
            $this->stopServer();
        }

        return $server->getExitCode();
    }

    /**
     * Start the watcher process for the server.
     *
     * @return \Symfony\Component\Process\Process|object
     */
    protected function startServerWatcher()
    {
        if (! $this->option('watch')) {
            return new class {
                public function __call($method, $parameters)
                {
                    return null;
                }
            };
        }

        if (empty($paths = config('octane.watch'))) {
            throw new InvalidArgumentException(
                'List of directories/files to watch not found. Please update your "config/octane.php" configuration file.',
            );
        }

        return tap(new Process([
            (new ExecutableFinder)->find('node'),
            'file-watcher.js',
            json_encode(collect(config('octane.watch'))->map(fn ($path) => base_path($path))),
        ], realpath(__DIR__.'/../../../bin'), null, null, null))->start();
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

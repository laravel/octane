<?php

namespace Laravel\Octane\Commands\Concerns;

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
                }

                usleep(500 * 1000);
            }

            $this->writeServerOutput($server);
        } catch (ServerShutdownException $e) {
            return 1;
        } finally {
            $this->callSilent('octane:stop', [
                '--server' => $type,
            ]);

            $watcher->stop();
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

        return tap(new Process([
            (new ExecutableFinder)->find('node'), 'file-watcher.js', base_path(),
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
            '  <fg=yellow>Use Ctrl+C to stop the server</>',
            '',
        ]);
    }
}

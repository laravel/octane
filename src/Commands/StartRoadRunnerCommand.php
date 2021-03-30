<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use Laravel\Octane\RoadRunner\ServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerStateFile;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class StartRoadRunnerCommand extends Command
{
    use Concerns\InstallsRoadRunnerDependencies;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:roadrunner
                    {--host=127.0.0.1 : The IP address the server should bind to}
                    {--port=8000 : The port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--watch : Automatically reload the server when the application is modified}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Start the Octane RoadRunner server';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Handle the command.
     *
     * @param  \Laravel\Octane\RoadRunner\ServerProcessInspector  $processInspector
     * @param  \Laravel\Octane\RoadRunner\ServerStateFile  $serverStateFile
     * @return int
     */
    public function handle(
        ServerProcessInspector $processInspector,
        ServerStateFile $serverStateFile
    ) {
        $this->ensureRoadRunnerPackageIsInstalled();

        $roadRunnerBinary = $this->ensureRoadRunnerBinaryIsInstalled();

        if ($processInspector->serverIsRunning()) {
            $this->error('RoadRunner server is already running.');

            return 1;
        }

        $this->writeServerStateFile($serverStateFile);

        $this->writeServerStartMessage();

        $serverProcess = tap(new Process(array_filter([
            $roadRunnerBinary,
            '-o', 'http.address='.$this->option('host').':'.$this->option('port'),
            '-o', 'http.workers.command=php ./vendor/bin/roadrunner-worker',
            '-o', 'http.workers.pool.numWorkers='.$this->workerCount(),
            '-o', 'http.workers.pool.maxJobs='.$this->option('max-requests'),
            '-o', 'http.static.dir=public',
            'serve',
            app()->environment('local') ? '-d' : null,
            '-l', 'json',
        ]), base_path(), ['APP_BASE_PATH' => base_path()], null, null))->start();

        $watcherProcess = $this->startWatcherProcess();

        while (! $serverProcess->isStarted()) {
            sleep(1);
        }

        $serverStateFile->writeProcessId($serverProcess->getPid());

        while ($serverProcess->isRunning()) {
            $this->writeServerProcessOutput($serverProcess);

            if ($watcherProcess->isRunning() &&
                $watcherProcess->getIncrementalOutput()) {
                $this->info('Application change detected. Restarting workers…');

                $processInspector->reloadServer(base_path());
            }

            usleep(500 * 1000);
        }

        $this->writeServerProcessOutput($serverProcess);

        $watcherProcess->stop();

        return $serverProcess->getExitCode();
    }

    /**
     * Get the watcher process for the RoadRunner server.
     *
     * @return \Symfony\Component\Process\Process|object
     */
    protected function startWatcherProcess()
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
        ], realpath(__DIR__.'/../../bin'), null, null, null))->start();
    }

    /**
     * Write the RoadRunner server state file.
     *
     * @param  \Laravel\Octane\RoadRunner\ServerStateFile  $serverStateFile
     * @return void
     */
    protected function writeServerStateFile(
        ServerStateFile $serverStateFile
    ) {
        $serverStateFile->writeState([
            'appName' => config('app.name', 'Laravel'),
            'host' => $this->option('host'),
            'port' => $this->option('port'),
            'workers' => $this->workerCount(),
            'maxRequests' => $this->option('max-requests'),
            'octaneConfig' => config('octane'),
        ]);
    }

    /**
     * Get the number of workers that should be started.
     *
     * @return int
     */
    protected function workerCount()
    {
        return $this->option('workers') === 'auto'
                            ? 1
                            : $this->option('workers', 1);
    }

    /**
     * Write the server start message to the console.
     *
     * @return void
     */
    protected function writeServerStartMessage()
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

    /**
     * Write the server process output to the console.
     *
     * @param  \Symfony\Component\Process\Process  $serverProcess
     * @return void
     */
    protected function writeServerProcessOutput($serverProcess)
    {
        Str::of($serverProcess->getIncrementalOutput())
            ->explode("\n")
            ->filter()
            ->each(fn ($output) => $this->info($output));

        Str::of($serverProcess->getIncrementalErrorOutput())
            ->explode("\n")
            ->filter()
            ->each(function ($output) {
                if (! is_array($debug = json_decode($output, true))) {
                    return $this->error($output);
                }

                if ($debug['level'] == 'info'
                    && Str::startsWith($debug['msg'], $this->option('host').' {')) {
                    [$_, $duration, $statusCode, $method, $url] = explode(' ', $debug['msg']);

                    return $this->requestInfo([
                        'method' => $method,
                        'url' => $url,
                        'statusCode' => $statusCode,
                        'duration' => (float) substr($duration, 1, -3),
                    ]);
                }
            });
    }
}

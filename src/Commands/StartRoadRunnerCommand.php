<?php

namespace Laravel\Octane\Commands;

use Illuminate\Console\Command;
use Laravel\Octane\RoadRunner\ServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerStateFile;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class StartRoadRunnerCommand extends Command
{
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
        $roadRunnerBinary = $this->ensureRoadRunnerBinaryIsInstalled();

        if ($processInspector->serverIsRunning()) {
            $this->error('RoadRunner server is already running.');

            return 1;
        }

        $this->writeServerStateFile($serverStateFile);

        $this->line('<info>Starting Octane server:</info> '.$this->option('host').':'.$this->option('port'));

        $serverProcess = tap(new Process(array_filter([
            $roadRunnerBinary,
            '-o', 'http.address='.$this->option('host').':'.$this->option('port'),
            '-o', 'http.workers.command=php ./vendor/bin/roadrunner-worker',
            '-o', 'http.workers.pool.numWorkers='.$this->workerCount(),
            '-o', 'http.workers.pool.maxJobs='.$this->option('max-requests'),
            'serve',
            app()->environment('local') ? '-d' : null,
        ]), base_path(), null, null, null))->start();

        $watcherProcess = $this->startWatcherProcess();

        while (! $serverProcess->isStarted()) {
            sleep(1);
        }

        $serverStateFile->writeProcessId($serverProcess->getPid());

        while ($serverProcess->isRunning()) {
            fwrite(STDOUT, $serverProcess->getIncrementalOutput());
            fwrite(STDERR, $serverProcess->getIncrementalErrorOutput());

            if ($watcherProcess->isRunning() &&
                $watcherProcess->getIncrementalOutput()) {
                fwrite(STDERR, "Application change detected. Restarting workers...\n");

                $processInspector->reloadServer(base_path());
            }

            usleep(500 * 1000);
        }

        fwrite(STDOUT, $serverProcess->getIncrementalOutput());
        fwrite(STDERR, $serverProcess->getIncrementalErrorOutput());

        $watcherProcess->stop();

        return $serverProcess->getExitCode();
    }

    /**
     * Ensure the RoadRunner binary is installed into the project.
     *
     * @return string
     */
    protected function ensureRoadRunnerBinaryIsInstalled(): string
    {
        if (file_exists(base_path('rr'))) {
            return base_path('rr');
        }

        if (! is_null($roadRunnerBinary = (new ExecutableFinder)->find('rr', null, [base_path()]))) {
            return $roadRunnerBinary;
        }

        if ($this->confirm('Unable to locate RoadRunner binary. Should Octane download the binary for your operating system?', true)) {
            tap(new Process(array_filter([
                './vendor/bin/rr',
                'get-binary',
            ]), base_path(), null, null, null))->run(
                fn ($type, $buffer) => $this->output->write($buffer)
            );

            $this->line('');
        }

        return base_path('rr');
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
}

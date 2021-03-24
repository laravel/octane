<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use Laravel\Octane\Swoole\ServerProcessInspector;
use Laravel\Octane\Swoole\ServerStateFile;
use Laravel\Octane\Swoole\SwooleExtension;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class StartSwooleCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:swoole
                    {--host=127.0.0.1 : The IP address the server should bind to}
                    {--port=8000 : The port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--task-workers=auto : The number of task workers that should be available to handle tasks}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--watch : Automatically reload the server when the application is modified}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Start the Octane Swoole server';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Handle the command.
     *
     * @param  \Laravel\Octane\Swoole\ServerProcessInspector  $processInspector
     * @param  \Laravel\Octane\Swoole\ServerStateFile  $serverStateFile
     * @param  \Laravel\Octane\Swoole\SwooleExtension  $extension
     * @return int
     */
    public function handle(
        ServerProcessInspector $processInspector,
        ServerStateFile $serverStateFile,
        SwooleExtension $extension
    ) {
        if ($processInspector->serverIsRunning()) {
            $this->error('Swoole server is already running.');

            return 1;
        }

        $this->writeServerStateFile($serverStateFile, $extension);

        $this->writeServerStartMessage();

        $serverProcess = tap(new Process([
            (new PhpExecutableFinder)->find(), 'swoole-server', $serverStateFile->path(),
        ], realpath(__DIR__.'/../../bin'), ['APP_BASE_PATH' => base_path()], null, null))->start();

        $watcherProcess = $this->startWatcherProcess();

        while ($serverProcess->isRunning()) {
            $this->writeServerProcessOutput($serverProcess);

            if ($watcherProcess->isRunning() &&
                $watcherProcess->getIncrementalOutput()) {
                $this->info('Application change detected. Restarting workers…');

                $processInspector->reloadServer();
            }

            usleep(500 * 1000);
        }

        $this->writeServerProcessOutput($serverProcess);

        $watcherProcess->stop();

        return $serverProcess->getExitCode();
    }

    /**
     * Get the watcher process for the Swoole server.
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
     * Write the Swoole server state file.
     *
     * @param  \Laravel\Octane\Swoole\ServerStateFile  $serverStateFile
     * @param  \Laravel\Octane\SwooleExtension  $extension
     * @return void
     */
    protected function writeServerStateFile(
        ServerStateFile $serverStateFile,
        SwooleExtension $extension
    ) {
        $serverStateFile->writeState([
            'appName' => config('app.name', 'Laravel'),
            'host' => $this->option('host'),
            'port' => $this->option('port'),
            'workers' => $this->workerCount($extension),
            'taskWorkers' => $this->taskWorkerCount($extension),
            'maxRequests' => $this->option('max-requests'),
            'publicPath' => public_path(),
            'storagePath' => storage_path(),
            'defaultServerOptions' => $this->defaultServerOptions($extension),
            'octaneConfig' => config('octane'),
        ]);
    }

    /**
     * Get the default Swoole server options.
     *
     * @param  \Laravel\Swoole\SwooleExtension  $extension
     * @return array
     */
    protected function defaultServerOptions(SwooleExtension $extension)
    {
        return [
            'buffer_output_size' => 10 * 1024 * 1024,
            'enable_coroutine' => false,
            'daemonize' => false,
            'log_file' => storage_path('logs/swoole_http.log'),
            'max_request' => $this->option('max-requests'),
            'package_max_length' => 20 * 1024 * 1024,
            'reactor_num' => $this->workerCount($extension),
            'send_yield' => true,
            'socket_buffer_size' => 128 * 1024 * 1024,
            'task_worker_num' => $this->taskWorkerCount($extension),
            'worker_num' => $this->workerCount($extension),
        ];
    }

    /**
     * Get the number of workers that should be started.
     *
     * @param  \Laravel\Octane\Swoole\SwooleExtension  $extension
     * @return int
     */
    protected function workerCount(SwooleExtension $extension)
    {
        return $this->option('workers') === 'auto'
                    ? $extension->cpuCount()
                    : $this->option('workers', 1);
    }

    /**
     * Get the number of task workers that should be started.
     *
     * @param  \Laravel\Octane\Swoole\SwooleExtension  $extension
     * @return int
     */
    protected function taskWorkerCount(SwooleExtension $extension)
    {
        return $this->option('task-workers') === 'auto'
                    ? $extension->cpuCount()
                    : $this->option('task-workers', 1);
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
     * Write the server process output ot the console.
     *
     * @param  \Symfony\Component\Process\Process  $serverProcess
     * @return void
     */
    protected function writeServerProcessOutput($serverProcess)
    {
        Str::of($serverProcess->getIncrementalOutput())
            ->explode("\n")
            ->each(fn ($output) => ! is_array($request = json_decode($output, true))
                ? $this->info($output)
                : $this->requestInfo($request));

        Str::of($serverProcess->getIncrementalErrorOutput())
            ->explode("\n")
            ->each(fn ($output) => ! is_array($throwable = json_decode($output, true))
                ? $this->error($output)
                : $this->throwableInfo($throwable));
    }
}

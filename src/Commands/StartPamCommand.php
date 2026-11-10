<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use Laravel\Octane\Pam\ServerProcessInspector;
use Laravel\Octane\Pam\ServerStateFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'octane:pam')]
class StartPamCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InstallsPamDependencies,
        Concerns\InteractsWithEnvironmentVariables,
        Concerns\InteractsWithServers;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:pam
                    {--host= : The IP address the server should bind to}
                    {--port= : The port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--watch : Automatically reload the server when the application is modified}
                    {--poll : Use file system polling while watching in order to watch files over a network}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Start the Octane PAM server';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle(ServerProcessInspector $inspector, ServerStateFile $serverStateFile)
    {
        $pamBinary = $this->ensurePamBinaryIsInstalled();

        if ($inspector->serverIsRunning()) {
            $this->components->error('PAM server is already running.');

            return 1;
        }

        $this->ensurePortIsAvailable();

        $this->ensurePamBinaryMeetsRequirements($pamBinary);

        $this->writeServerStateFile($serverStateFile);

        $this->forgetEnvironmentVariables();

        $server = tap(new Process([
            $pamBinary,
            'start',
            realpath(__DIR__.'/../../bin/pam-worker.php'),
            ...($this->workerCount() ? ['--workers', (string) $this->workerCount()] : []),
            '--max-requests', $this->option('max-requests'),
        ], base_path(), [
            'APP_ENV' => app()->environment(),
            'APP_BASE_PATH' => base_path(),
            'LARAVEL_OCTANE' => 1,
            'OCTANE_HOST' => $this->getHost(),
            'OCTANE_PORT' => $this->getPort(),
            'REQUEST_MAX_EXECUTION_TIME' => config('octane.max_execution_time', 30),
        ], null, null))->start();

        $serverStateFile->writeProcessId($server->getPid());

        return $this->runServer($server, $inspector, 'pam');
    }

    /**
     * Write the PAM server state file.
     *
     * @return void
     */
    protected function writeServerStateFile(ServerStateFile $serverStateFile)
    {
        $serverStateFile->writeState([
            'appName' => config('app.name', 'Laravel'),
            'host' => $this->getHost(),
            'port' => $this->getPort(),
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
            ? 0
            : (int) $this->option('workers');
    }

    /**
     * Write the server process output to the console.
     *
     * @param  \Symfony\Component\Process\Process  $server
     * @return void
     */
    protected function writeServerOutput($server)
    {
        [$output, $errorOutput] = $this->getServerOutput($server);

        Str::of($output)
            ->explode("\n")
            ->filter()
            ->each(fn ($output) => is_array($stream = json_decode($output, true))
                ? $this->handleStream($stream)
                : $this->components->info($output)
            );

        Str::of($errorOutput)
            ->explode("\n")
            ->filter()
            ->reject(fn ($output) => Str::contains($output, ['READY', 'Pam listening on', 'zero-downtime reload']) ||
                preg_match('/worker=\d+\s*$/', $output))
            ->each(function ($output) {
                if (! is_array($debug = json_decode($output, true))) {
                    return $this->raw($output);
                }

                if (isset($debug['type'])) {
                    return $this->handleStream($debug);
                }

                // Requests are already written by the Octane worker...
                if (($debug['event'] ?? null) === 'http.request') {
                    return;
                }

                $message = $debug['message'] ?? $debug['event'] ?? $output;

                match ($debug['level'] ?? 'info') {
                    'error' => $this->components->error($message),
                    'warn', 'warning' => $this->components->warn($message),
                    default => $this->components->info($message),
                };
            });
    }

    /**
     * Stop the server.
     *
     * @return void
     */
    protected function stopServer()
    {
        $this->callSilent('octane:stop', [
            '--server' => 'pam',
        ]);
    }
}

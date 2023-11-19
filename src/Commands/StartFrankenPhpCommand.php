<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Octane\FrankenPhp\ServerProcessInspector;
use Laravel\Octane\FrankenPhp\ServerStateFile;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\Process;

class StartFrankenPhpCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InstallsFrankenPhpDependencies,
        Concerns\InteractsWithEnvironmentVariables,
        Concerns\InteractsWithServers;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:frankenphp
                    {--host=127.0.0.1 : The IP address the server should bind to}
                    {--port= : The port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--frankenphp-config= : The path to the FrankenPHP Caddyfile file}
                    {--https : Enable HTTPS, HTTP/2 and HTTP/3, automatically generate and renew certificates}
                    {--watch : Automatically reload the server when the application is modified}
                    {--poll : Use file system polling while watching in order to watch files over a network}
                    {--log-level= : Log messages at or above the specified log level}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Start the Octane FrankenPHP server';

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
        $frankenphpBinary = $this->ensureFrankenPhpBinaryIsInstalled();

        if ($inspector->serverIsRunning()) {
            $this->error('FrankenPHP server is already running.');

            return 1;
        }

        $this->writeServerStateFile($serverStateFile);

        $this->forgetEnvironmentVariables();

        $host = $this->option('host');

        $process = tap(new Process([
            $frankenphpBinary,
            'run',
            '-c', $this->configPath(),
        ], base_path(), [
            'APP_ENV' => app()->environment(),
            'APP_BASE_PATH' => base_path(),
            'APP_PUBLIC_PATH' => public_path(),
            'LARAVEL_OCTANE' => 1,
            'SERVER_NAME' => ($this->option('https') ? 'https://' : 'http://')."$host:".$this->getPort(),
            'WORKER_COUNT' => $this->workerCount() ?: '',
            'MAX_REQUESTS' => $this->option('max-requests'),
            'CADDY_SERVER_EXTRA_DIRECTIVES' => $this->getMercureConfig(),
        ]));
        $process->setPty($this->input->isInteractive());
        $server = $process->start();

        $serverStateFile->writeProcessId($server->getPid());

        return $this->runServer($server, $inspector, 'frankenphp');
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
            : $this->option('workers');
    }

    /**
     * Get the path to the FrankenPHP configuration file.
     *
     * @return string
     */
    protected function configPath()
    {
        $path = $this->option('frankenphp-config');

        if (! $path) {
            $basePath = base_path('Caddyfile');
            touch($basePath);

            return $basePath;
        }

        if ($path && ! realpath($path)) {
            throw new InvalidArgumentException('Unable to locate specified configuration file.');
        }

        return realpath($path);
    }

    /**
     * Write the FrankenPHP server state file.
     *
     * @return void
     */
    protected function writeServerStateFile(
        ServerStateFile $serverStateFile
    ) {
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
            ->each(function ($output) {
                $this->raw($output);
            });

        Str::of($errorOutput)
            ->explode("\n")
            ->filter()
            ->each(function ($output) {
                $this->raw($output);
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
            '--server' => 'frankenphp',
        ]);
    }

    /**
     * Generate the Mercure configuration snippet to include in the Caddyfile.
     *
     * @return string
     */
    private function getMercureConfig()
    {
        if (! $mercure = (config('octane')['mercure'] ?? false)) {
            return '';
        }

        $config = 'mercure {';
        foreach ($mercure as $key => $value) {
            if ($value === false) {
                continue;
            }
            if ($value === true) {
                $config .= "\n\t\t\t$key";

                continue;
            }

            $config .= "\n\t\t\t$key $value";
        }

        if (
            ! isset($mercure['demo']) &&
            ($this->option('log-level') === 'debug' || app()->environment('local'))
        ) {
            $config .= "\n\t\t\tdemo";
        }

        return "$config\n\t\t}";
    }
}

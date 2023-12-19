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
        Concerns\InteractsWithServers {
            Concerns\InteractsWithServers::writeServerRunningMessage as baseWriteServerRunningMessage;
        }

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
                    {--caddyfile= : The path to the FrankenPHP Caddyfile file}
                    {--https : Enable HTTPS, HTTP/2, and HTTP/3, and automatically generate and renew certificates}
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
        $this->ensureFrankenPhpWorkerIsInstalled();

        $frankenphpBinary = $this->ensureFrankenPhpBinaryIsInstalled();

        if ($inspector->serverIsRunning()) {
            $this->error('FrankenPHP server is already running.');

            return 1;
        }

        $this->ensureFrankenPhpBinaryMeetsRequirements($frankenphpBinary);

        $this->writeServerStateFile($serverStateFile);

        $this->forgetEnvironmentVariables();

        $host = $this->option('host');
        $port = $this->getPort();

        $serverName = $this->option('https')
            ? "https://$host:$port"
            : "http://:$port";

        $process = tap(new Process([
            $frankenphpBinary,
            'run',
            '-c', $this->configPath(),
        ], base_path(), [
            'APP_ENV' => app()->environment(),
            'APP_BASE_PATH' => base_path(),
            'APP_PUBLIC_PATH' => public_path(),
            'LARAVEL_OCTANE' => 1,
            'MAX_REQUESTS' => $this->option('max-requests'),
            'CADDY_SERVER_LOG_LEVEL' => $this->option('log-level') ?: (app()->environment('local') ? 'INFO' : 'WARN'),
            'CADDY_SERVER_LOGGER' => 'json',
            'CADDY_SERVER_SERVER_NAME' => $serverName,
            'CADDY_SERVER_WORKER_COUNT' => $this->workerCount() ?: '',
            'CADDY_SERVER_EXTRA_DIRECTIVES' => $this->buildMercureConfig(),
        ]));

        $server = $process->start();

        $serverStateFile->writeProcessId($server->getPid());

        return $this->runServer($server, $inspector, 'frankenphp');
    }

    /**
     * Get the path to the FrankenPHP configuration file.
     *
     * @return string
     */
    protected function configPath()
    {
        $path = $this->option('caddyfile') ?: __DIR__.'/stubs/Caddyfile';

        $path = realpath($path);

        if (! $path) {
            throw new InvalidArgumentException('Unable to locate specified configuration file.');
        }

        return $path;
    }

    /**
     * Generate the Mercure configuration snippet to include in the Caddyfile.
     *
     * @return string
     */
    protected function buildMercureConfig()
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

        return "$config\n\t\t}";
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
     * Write the server process output to the console.
     *
     * @param  \Symfony\Component\Process\Process  $server
     * @return void
     */
    protected function writeServerOutput($server)
    {
        [$_, $errorOutput] = $this->getServerOutput($server);

        $errorOutput = Str::of($errorOutput)
            ->explode("\n")
            ->filter()
            ->values();

        if ($this->option('log-level') !== null) {
            return $errorOutput->each(fn ($output) => $this->raw($output));
        }

        $errorOutput->each(function ($output) {
            if (! is_array($debug = json_decode($output, true))) {
                return $this->info($output);
            }

            if (is_array($stream = json_decode($debug['msg'], true))) {
                return $this->handleStream($stream);
            }

            if ($debug['msg'] == 'handled request') {
                if (! $this->laravel->isLocal()) {
                    return;
                }

                [
                    'duration' => $duration,
                    'request' => [
                        'method' => $method,
                        'uri' => $url,
                    ],
                    'status' => $statusCode,
                    'request' => $request,
                ] = $debug;

                if (str_starts_with($url, '/.well-known/mercure')) {
                    return;
                }

                return $this->requestInfo([
                    'method' => $method,
                    'url' => $url,
                    'statusCode' => $statusCode,
                    'duration' => (float) $duration * 1000,
                ]);
            }

            if ($debug['level'] === 'warn') {
                return $this->warn($debug['msg']);
            }

            if ($debug['level'] !== 'info') {
                return $this->error($debug['msg']);
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    protected function writeServerRunningMessage()
    {
        if ($this->option('log-level') === null) {
            $this->baseWriteServerRunningMessage();
        }
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
}

<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Octane\FrankenPhp\ServerProcessInspector;
use Laravel\Octane\FrankenPhp\ServerStateFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'octane:frankenphp')]
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
                    {--admin-host=localhost : The host the admin server should be available on}
                    {--admin-port=2019 : The port the admin server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--caddyfile= : The path to the FrankenPHP Caddyfile file}
                    {--https : Enable HTTPS, HTTP/2, and HTTP/3, and automatically generate and renew certificates}
                    {--http-redirect : Enable HTTP to HTTPS redirection (only enabled if --https is passed)}
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
        $this->ensurePortIsAvailable();

        $frankenphpBinary = $this->ensureFrankenPhpBinaryIsInstalled();

        if ($inspector->serverIsRunning()) {
            $this->components->error('FrankenPHP server is already running.');

            return 1;
        }

        $this->ensureFrankenPhpBinaryMeetsRequirements($frankenphpBinary);

        $this->writeServerStateFile($serverStateFile);

        $this->forgetEnvironmentVariables();

        $host = $this->getHost();
        $port = $this->getPort();

        $https = $this->option('https');

        $serverName = $https
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
            'REQUEST_MAX_EXECUTION_TIME' => $this->maxExecutionTime(),
            'CADDY_GLOBAL_OPTIONS' => ($https && $this->option('http-redirect')) ? '' : 'auto_https disable_redirects',
            'CADDY_SERVER_ADMIN_PORT' => $this->adminPort(),
            'CADDY_SERVER_ADMIN_HOST' => $this->option('admin-host'),
            'CADDY_SERVER_LOG_LEVEL' => $this->option('log-level') ?: (app()->environment('local') ? 'INFO' : 'WARN'),
            'CADDY_SERVER_LOGGER' => 'json',
            'CADDY_SERVER_SERVER_NAME' => $serverName,
            'CADDY_SERVER_WORKER_COUNT' => $this->workerCount() ?: '',
            'CADDY_SERVER_WORKER_DIRECTIVE' => $this->workerCount() ? "num {$this->workerCount()}" : '',
            'CADDY_SERVER_EXTRA_DIRECTIVES' => $this->buildMercureConfig(),
            'CADDY_SERVER_WATCH_DIRECTIVES' => $this->buildWatchConfig(),
        ]));

        $server = $process->start();

        $serverStateFile->writeProcessId($server->getPid());

        return $this->runServer($server, $inspector, 'frankenphp');
    }

    /**
     * Ensures the server and admin localhost ports are available.
     *
     * @return void
     */
    protected function ensurePortIsAvailable()
    {
        $host = $this->getHost();

        $serverPort = $this->getPort();
        $adminPort = $this->adminPort();

        if ($host !== '127.0.0.1') {
            return;
        }

        foreach ([$serverPort, $adminPort] as $port) {
            $connection = @fsockopen($host, $port);
            $isAvailable = ! is_resource($connection);

            if (! $isAvailable) {
                @fclose($connection);

                throw new InvalidArgumentException("Unable to start server. Port {$port} is already in use.");
            }
        }
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
     * Generate the file watcher configuration snippet to include in the Caddyfile.
     *
     * @return string
     */
    protected function buildWatchConfig()
    {
        if (! $this->option('watch')) {
            return '';
        }

        // If paths are not specified, fall back to FrankenPHP's default watcher pattern
        if (empty($paths = config('octane.watch'))) {
            return "\t\twatch";
        }

        return collect($paths)->map(fn ($path) => "\t\twatch ".base_path($path))->join("\n");
    }

    /**
     * Get the maximum number of seconds that workers should be allowed to execute a single request.
     *
     * @return int
     */
    protected function maxExecutionTime()
    {
        return config('octane.max_execution_time', 30);
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
            'adminHost' => $this->option('admin-host'),
            'adminPort' => $this->adminPort(),
            'workers' => $this->workerCount(),
            'maxRequests' => $this->option('max-requests'),
            'octaneConfig' => config('octane'),
        ]);
    }

    /**
     * Get the port the admin URL should be available on.
     *
     * @return int
     */
    protected function adminPort()
    {
        if ($this->option('admin-port')) {
            return (int) $this->option('admin-port');
        }

        $defaultPort = 2019;

        return tap($defaultPort + ($this->getPort() - 8000), function ($adminPort) {
            if ($adminPort < 0) {
                throw new InvalidArgumentException(
                    'Unable to determine admin port. Please specify the [--admin-port] option.',
                );
            }
        });
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
                return $this->components->info($output);
            }

            $message = $debug['msg'] ?? 'unknown error';

            if (is_array($stream = json_decode($message, true))) {
                return $this->handleStream($stream);
            }

            if ($message == 'handled request') {
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

            if (isset($debug['level'])) {
                if ($debug['level'] === 'warn') {
                    return $this->components->warn($message);
                }

                if ($debug['level'] !== 'info') {
                    // Request timeout...
                    if (isset($debug['exit_status']) && $debug['exit_status'] === 255) {
                        return;
                    }

                    return $this->components->error($message);
                }
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

    /**
     * Always return a no-op object, because FrankenPHP has native
     * watcher support, so there is no need for an external watcher.
     */
    protected function startServerWatcher()
    {
        return new class
        {
            public function __call($method, $parameters)
            {
                return null;
            }
        };
    }
}

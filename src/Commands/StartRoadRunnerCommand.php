<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Octane\RoadRunner\ServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerStateFile;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class StartRoadRunnerCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InstallsRoadRunnerDependencies,
        Concerns\InteractsWithServers,
        Concerns\InteractsWithEnvironmentVariables;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:roadrunner
                    {--host=127.0.0.1 : The IP address the server should bind to}
                    {--port= : The port the server should be available on}
                    {--rpc-host= : The RPC IP address the server should bind to}
                    {--rpc-port= : The RPC port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--rr-config= : The path to the RoadRunner .rr.yaml file}
                    {--watch : Automatically reload the server when the application is modified}
                    {--poll : Use file system polling while watching in order to watch files over a network}
                    {--log-level= : Log messages at or above the specified log level}';

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
     * @param  \Laravel\Octane\RoadRunner\ServerProcessInspector  $inspector
     * @param  \Laravel\Octane\RoadRunner\ServerStateFile  $serverStateFile
     * @return int
     */
    public function handle(ServerProcessInspector $inspector, ServerStateFile $serverStateFile)
    {
        if (! $this->isRoadRunnerInstalled()) {
            $this->error('RoadRunner not installed. Please execute the `octane:install` Artisan command.');

            return 1;
        }

        $roadRunnerBinary = $this->ensureRoadRunnerBinaryIsInstalled();

        if ($inspector->serverIsRunning()) {
            $this->error('RoadRunner server is already running.');

            return 1;
        }

        $this->ensureRoadRunnerBinaryMeetsRequirements($roadRunnerBinary);

        $this->writeServerStateFile($serverStateFile);

        $this->forgetEnvironmentVariables();

        $server = tap(new Process(array_filter([
            $roadRunnerBinary,
            '-c', $this->configPath(),
            '-o', 'version=2.7',
            '-o', 'http.address='.$this->option('host').':'.$this->getPort(),
            '-o', 'server.command='.(new PhpExecutableFinder)->find().' '.base_path(config('octane.roadrunner.command', 'vendor/bin/roadrunner-worker')),
            '-o', 'http.pool.num_workers='.$this->workerCount(),
            '-o', 'http.pool.max_jobs='.$this->option('max-requests'),
            '-o', 'rpc.listen=tcp://'.$this->rpcHost().':'.$this->rpcPort(),
            '-o', 'http.pool.supervisor.exec_ttl='.$this->maxExecutionTime(),
            '-o', 'http.static.dir='.base_path('public'),
            '-o', 'http.middleware='.config('octane.roadrunner.http_middleware', 'static'),
            '-o', 'logs.mode=production',
            '-o', 'logs.level='.($this->option('log-level') ?: (app()->environment('local') ? 'debug' : 'warn')),
            '-o', 'logs.output=stdout',
            '-o', 'logs.encoding=json',
            'serve',
        ]), base_path(), [
            'APP_ENV' => app()->environment(),
            'APP_BASE_PATH' => base_path(),
            'LARAVEL_OCTANE' => 1,
        ]))->start();

        $serverStateFile->writeProcessId($server->getPid());

        return $this->runServer($server, $inspector, 'roadrunner');
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
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'rpcPort' => $this->rpcPort(),
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
        return $this->option('workers') == 'auto'
                            ? 0
                            : $this->option('workers');
    }

    /**
     * Get the path to the RoadRunner configuration file.
     *
     * @return string
     */
    protected function configPath()
    {
        $path = $this->option('rr-config');

        if (! $path) {
            touch(base_path('.rr.yaml'));

            return base_path('.rr.yaml');
        }

        if ($path && ! realpath($path)) {
            throw new InvalidArgumentException('Unable to locate specified configuration file.');
        }

        return realpath($path);
    }

    /**
     * Get the maximum number of seconds that workers should be allowed to execute a single request.
     *
     * @return string
     */
    protected function maxExecutionTime()
    {
        return config('octane.max_execution_time', '30').'s';
    }

    /**
     * Get the RPC IP address the server should be available on.
     *
     * @return int
     */
    protected function rpcHost()
    {
        return $this->option('rpc-host') ?: $this->getHost();
    }

    /**
     * Get the RPC port the server should be available on.
     *
     * @return int
     */
    protected function rpcPort()
    {
        return $this->option('rpc-port') ?: $this->getPort() - 1999;
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
                if (! is_array($debug = json_decode($output, true))) {
                    return $this->info($output);
                }

                if (is_array($stream = json_decode($debug['msg'], true))) {
                    return $this->handleStream($stream);
                }

                if ($debug['logger'] == 'server') {
                    return $this->raw($debug['msg']);
                }

                if ($debug['level'] == 'info'
                    && isset($debug['remote_address'])
                    && isset($debug['msg'])
                    && $debug['msg'] == 'http log') {
                    [
                        'elapsed' => $elapsed,
                        'method' => $method,
                        'status' => $statusCode,
                        'URI' => $url,
                    ] = $debug;

                    return $this->requestInfo([
                        'method' => $method,
                        'url' => $url,
                        'statusCode' => $statusCode,
                        'duration' => $this->calculateElapsedTime($elapsed),
                    ]);
                }
            });

        Str::of($errorOutput)
            ->explode("\n")
            ->filter()
            ->each(function ($output) {
                if (! Str::contains($output, ['DEBUG', 'INFO', 'WARN'])) {
                    $this->error($output);
                }
            });
    }

    /**
     * Calculate the elapsed time for a request.
     *
     * @param  string  $elapsed
     * @return float
     */
    protected function calculateElapsedTime(string $elapsed): float
    {
        if (Str::endsWith($elapsed, 'ms')) {
            return substr($elapsed, 0, -2);
        }

        if (Str::endsWith($elapsed, 'µs')) {
            return mb_substr($elapsed, 0, -2) * 0.001;
        }

        return (float) $elapsed * 1000;
    }

    /**
     * Stop the server.
     *
     * @return void
     */
    protected function stopServer()
    {
        $this->callSilent('octane:stop', [
            '--server' => 'roadrunner',
        ]);
    }
}

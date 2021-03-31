<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use Laravel\Octane\RoadRunner\ServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerStateFile;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\Process;

class StartRoadRunnerCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InstallsRoadRunnerDependencies, Concerns\InteractsWithServers;

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
     * @param  \Laravel\Octane\RoadRunner\ServerProcessInspector  $inspector
     * @param  \Laravel\Octane\RoadRunner\ServerStateFile  $serverStateFile
     * @return int
     */
    public function handle(
        ServerProcessInspector $inspector,
        ServerStateFile $serverStateFile
    ) {
        $this->ensureRoadRunnerPackageIsInstalled();

        $roadRunnerBinary = $this->ensureRoadRunnerBinaryIsInstalled();

        if ($inspector->serverIsRunning()) {
            $this->error('RoadRunner server is already running.');

            return 1;
        }

        $this->writeServerStateFile($serverStateFile);

        touch(base_path('.rr.yaml'));

        $server = tap(new Process(array_filter([
            $roadRunnerBinary,
            '-o', 'http.address='.$this->option('host').':'.$this->option('port'),
            '-o', 'server.command=php ./vendor/bin/roadrunner-worker',
            '-o', 'http.pool.num_workers='.$this->workerCount(),
            '-o', 'http.pool.max_jobs='.$this->option('max-requests'),
            '-o', 'http.static.dir=public',
            '-o', 'http.middleware=static',
            '-o', app()->environment('local') ? 'logs.mode=production' : 'logs.mode=none',
            '-o', app()->environment('local') ? 'logs.level=debug' : 'logs.level=warning',
            '-o', 'logs.output=stdout',
            '-o', 'logs.encoding=json',
            'serve',
        ]), base_path(), ['APP_BASE_PATH' => base_path()], null, null))->start();

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
        return $this->option('workers') == 'auto'
                            ? 0
                            : $this->option('workers', 0);
    }

    /**
     * Write the server process output to the console.
     *
     * @param  \Symfony\Component\Process\Process  $server
     * @return void
     */
    protected function writeServerOutput($server)
    {
        Str::of($server->getIncrementalOutput())
            ->explode("\n")
            ->filter()
            ->each(function ($output) {
                if (! is_array($debug = json_decode($output, true))) {
                    return $this->info($output);
                }

                if (is_array($stream = json_decode($debug['msg'], true))) {
                    return $this->handleStream($stream);
                }

                if ($debug['level'] == 'debug' && isset($debug['remote'])) {
                    [$statusCode, $method, $url] = explode(' ', $debug['msg']);

                    return $this->requestInfo([
                        'method' => $method,
                        'url' => $url,
                        'statusCode' => $statusCode,
                        'duration' => (float) substr($debug['elapsed'], 0, -2),
                    ]);
                }
            });

        Str::of($server->getIncrementalErrorOutput())
            ->explode("\n")
            ->filter()
            ->each(function ($output) {
                if (! Str::contains($output, ['DEBUG', 'INFO', 'WARN'])) {
                    $this->error($output);
                }
            });
    }
}

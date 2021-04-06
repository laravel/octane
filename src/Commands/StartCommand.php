<?php

namespace Laravel\Octane\Commands;

use Symfony\Component\Console\Command\SignalableCommandInterface;

class StartCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InteractsWithServers;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:start
                    {--server= : The server that should be used to serve the application}
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
    public $description = 'Start the Octane server';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        if (! app()->environment('local', 'testing')) {
            $this->warn('Laravel Octane is within a beta period. '
                .'It should only be used for local development and '
                .'testing in order to improve the quality of the library '
                .'and resolve any existing bugs.');
        }

        $server = $this->option('server') ?: config('octane.server');

        return $server == 'swoole'
            ? $this->startSwooleServer()
            : $this->startRoadRunnerServer();
    }

    /**
     * Start the Swoole server for Octane.
     *
     * @return int
     */
    protected function startSwooleServer()
    {
        return $this->call('octane:swoole', [
            '--host' => $this->option('host'),
            '--port' => $this->option('port'),
            '--workers' => $this->option('workers'),
            '--task-workers' => $this->option('task-workers'),
            '--max-requests' => $this->option('max-requests'),
            '--watch' => $this->option('watch'),
        ]);
    }

    /**
     * Start the RoadRunner server for Octane.
     *
     * @return int
     */
    protected function startRoadRunnerServer()
    {
        return $this->call('octane:roadrunner', [
            '--host' => $this->option('host'),
            '--port' => $this->option('port'),
            '--workers' => $this->option('workers'),
            '--max-requests' => $this->option('max-requests'),
            '--watch' => $this->option('watch'),
        ]);
    }
}

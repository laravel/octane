<?php

namespace Laravel\Octane\Commands;

use Laravel\Octane\FrankenPhp\ServerProcessInspector as FrankenPhpProcessInspector;
use Laravel\Octane\FrankenPhp\ServerStateFile as FrankenPhpStateFile;
use Laravel\Octane\RoadRunner\ServerProcessInspector as RoadRunnerServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use Laravel\Octane\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use Laravel\Octane\Swoole\ServerStateFile as SwooleServerStateFile;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'octane:stop')]
class StopCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:stop {--server= : The server that is running the application}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Stop the Octane server';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        $server = $this->option('server') ?: config('octane.server');

        return match ($server) {
            'swoole' => $this->stopSwooleServer(),
            'roadrunner' => $this->stopRoadRunnerServer(),
            'frankenphp' => $this->stopFrankenPhpServer(),
            default => $this->invalidServer($server),
        };
    }

    /**
     * Stop the Swoole server for Octane.
     *
     * @return int
     */
    protected function stopSwooleServer()
    {
        $inspector = app(SwooleServerProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            app(SwooleServerStateFile::class)->delete();

            $this->components->error('Swoole server is not running.');

            return 1;
        }

        $this->components->info('Stopping server...');

        if (! $inspector->stopServer()) {
            $this->components->error('Failed to stop Swoole server.');

            return 1;
        }

        app(SwooleServerStateFile::class)->delete();

        return 0;
    }

    /**
     * Stop the RoadRunner server for Octane.
     *
     * @return int
     */
    protected function stopRoadRunnerServer()
    {
        $inspector = app(RoadRunnerServerProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            app(RoadRunnerServerStateFile::class)->delete();

            $this->components->error('RoadRunner server is not running.');

            return 1;
        }

        $this->components->info('Stopping server...');

        $inspector->stopServer();

        app(RoadRunnerServerStateFile::class)->delete();

        return 0;
    }

    /**
     * Stop the FrankenPHP server for Octane.
     *
     * @return int
     */
    protected function stopFrankenPhpServer()
    {
        $inspector = app(FrankenPhpProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            app(FrankenPhpStateFile::class)->delete();

            $this->components->error('FrankenPHP server is not running.');

            return 1;
        }

        $this->components->info('Stopping server...');

        $inspector->stopServer();

        app(FrankenPhpStateFile::class)->delete();

        return 0;
    }

    /**
     * Inform the user that the server type is invalid.
     *
     * @return int
     */
    protected function invalidServer(string $server)
    {
        $this->components->error("Invalid server: {$server}.");

        return 1;
    }
}

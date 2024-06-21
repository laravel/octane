<?php

namespace Laravel\Octane\Commands;

use Laravel\Octane\FrankenPhp\ServerProcessInspector as FrankenPhpServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerProcessInspector as RoadRunnerServerProcessInspector;
use Laravel\Octane\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'octane:reload')]
class ReloadCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:reload {--server= : The server that is running the application}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Reload the Octane workers';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        $server = $this->option('server') ?: config('octane.server');

        return match ($server) {
            'swoole' => $this->reloadSwooleServer(),
            'roadrunner' => $this->reloadRoadRunnerServer(),
            'frankenphp' => $this->reloadFrankenPhpServer(),
            default => $this->invalidServer($server),
        };
    }

    /**
     * Reload the Swoole server for Octane.
     *
     * @return int
     */
    protected function reloadSwooleServer()
    {
        $inspector = app(SwooleServerProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            $this->components->error('Octane server is not running.');

            return 1;
        }

        $this->components->info('Reloading workers...');

        $inspector->reloadServer();

        return 0;
    }

    /**
     * Reload the RoadRunner server for Octane.
     *
     * @return int
     */
    protected function reloadRoadRunnerServer()
    {
        $inspector = app(RoadRunnerServerProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            $this->components->error('Octane server is not running.');

            return 1;
        }

        $this->components->info('Reloading workers...');

        $inspector->reloadServer();

        return 0;
    }

    /**
     * Reload the FrankenPHP server for Octane.
     *
     * @return int
     */
    protected function reloadFrankenPhpServer()
    {
        $inspector = app(FrankenPhpServerProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            $this->components->error('Octane server is not running.');

            return 1;
        }

        $this->components->info('Reloading workers...');

        $inspector->reloadServer();

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

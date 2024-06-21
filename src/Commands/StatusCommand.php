<?php

namespace Laravel\Octane\Commands;

use Laravel\Octane\FrankenPhp\ServerProcessInspector as FrankenPhpServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerProcessInspector as RoadRunnerServerProcessInspector;
use Laravel\Octane\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'octane:status')]
class StatusCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:status {--server= : The server that is running the application}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Get the current status of the Octane server';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        $server = $this->option('server') ?: config('octane.server');

        $isRunning = match ($server) {
            'swoole' => $this->isSwooleServerRunning(),
            'roadrunner' => $this->isRoadRunnerServerRunning(),
            'frankenphp' => $this->isFrankenPhpServerRunning(),
            default => $this->invalidServer($server),
        };

        return ! tap($isRunning, function ($isRunning) {
            $isRunning
                ? $this->components->info('Octane server is running.')
                : $this->components->info('Octane server is not running.');
        });
    }

    /**
     * Check if the Swoole server is running.
     *
     * @return bool
     */
    protected function isSwooleServerRunning()
    {
        return app(SwooleServerProcessInspector::class)
            ->serverIsRunning();
    }

    /**
     * Check if the RoadRunner server is running.
     *
     * @return bool
     */
    protected function isRoadRunnerServerRunning()
    {
        return app(RoadRunnerServerProcessInspector::class)
            ->serverIsRunning();
    }

    /**
     * Check if the FrankenPHP server is running.
     *
     * @return bool
     */
    protected function isFrankenPhpServerRunning()
    {
        return app(FrankenPhpServerProcessInspector::class)
            ->serverIsRunning();
    }

    /**
     * Inform the user that the server type is invalid.
     *
     * @return bool
     */
    protected function invalidServer(string $server)
    {
        $this->components->error("Invalid server: {$server}.");

        return false;
    }
}

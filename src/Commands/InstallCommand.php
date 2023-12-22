<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Octane\Swoole\SwooleExtension;
use Throwable;

class InstallCommand extends Command
{
    use Concerns\InstallsFrankenPhpDependencies,
        Concerns\InstallsRoadRunnerDependencies;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:install
                    {--server= : The server that should be used to serve the application}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Install the Octane components and resources';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        $server = $this->option('server') ?: $this->choice(
            'Which application server you would like to use?',
            ['roadrunner', 'swoole', 'frankenphp'],
        );

        return (int) ! tap(match ($server) {
            'swoole' => $this->installSwooleServer(),
            'roadrunner' => $this->installRoadRunnerServer(),
            'frankenphp' => $this->installFrankenPhpServer(),
            default => $this->invalidServer($server),
        }, function ($installed) use ($server) {
            if ($installed) {
                $this->updateEnvironmentFile($server);

                $this->callSilent('vendor:publish', ['--tag' => 'octane-config', '--force' => true]);

                $this->info('Octane installed successfully.');
                $this->newLine();
            }
        });
    }

    /**
     * Updates the environment file with the given server.
     *
     * @param  string  $server
     * @return void
     */
    public function updateEnvironmentFile($server)
    {
        if (File::exists($env = app()->environmentFile())) {
            $contents = File::get($env);

            if (! Str::contains($contents, 'OCTANE_SERVER=')) {
                File::append(
                    $env,
                    PHP_EOL.'OCTANE_SERVER='.$server.PHP_EOL,
                );
            } else {
                $this->warn('Please adjust the `OCTANE_SERVER` environment variable.');
            }
        }
    }

    /**
     * Install the RoadRunner dependencies.
     *
     * @return bool
     */
    public function installRoadRunnerServer()
    {
        if (! $this->ensureRoadRunnerPackageIsInstalled()) {
            return false;
        }

        if (File::exists(base_path('.gitignore'))) {
            collect(['rr', '.rr.yaml'])
                ->each(function ($file) {
                    $contents = File::get(base_path('.gitignore'));
                    if (! Str::contains($contents, $file.PHP_EOL)) {
                        File::append(
                            base_path('.gitignore'),
                            $file.PHP_EOL
                        );
                    }
                });
        }

        return $this->ensureRoadRunnerBinaryIsInstalled();
    }

    /**
     * Install the Swoole dependencies.
     *
     * @return bool
     */
    public function installSwooleServer()
    {
        if (! resolve(SwooleExtension::class)->isInstalled()) {
            $this->warn('The Swoole extension is missing.');
        }

        return true;
    }

    /**
     * Install the FrankenPHP server.
     *
     * @return bool
     */
    public function installFrankenPhpServer()
    {
        if (! $this->confirm("FrankenPHP's Octane integration is in beta and should be used with caution in production. Do you wish to continue?")) {
            return false;
        }

        $gitIgnorePath = base_path('.gitignore');

        if (File::exists($gitIgnorePath)) {
            $contents = File::get($gitIgnorePath);

            $filesToAppend = collect(['/caddy', 'frankenphp', 'frankenphp-worker.php'])
                ->filter(fn ($file) => ! str_contains($contents, $file.PHP_EOL))
                ->implode(PHP_EOL);

            if ($filesToAppend !== '') {
                File::append($gitIgnorePath, PHP_EOL.$filesToAppend.PHP_EOL);
            }
        }

        $this->ensureFrankenPhpWorkerIsInstalled();

        try {
            $this->ensureFrankenPhpBinaryIsInstalled();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Inform the user that the server type is invalid.
     *
     * @return bool
     */
    protected function invalidServer(string $server)
    {
        $this->error("Invalid server: {$server}.");

        return false;
    }
}

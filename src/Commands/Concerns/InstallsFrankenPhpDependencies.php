<?php

namespace Laravel\Octane\Commands\Concerns;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\FrankenPhp\Concerns\FindsFrankenPhpBinary;
use Symfony\Component\Process\Process;
use Throwable;

trait InstallsFrankenPhpDependencies
{
    use FindsFrankenPhpBinary;

    /**
     * The minimum required version of the FrankenPHP binary.
     *
     * @var string
     */
    protected $requiredFrankenPhpVersion = '1.0.0';

    /**
     * Ensure the FrankenPHP binary is installed into the project.
     *
     * @return string
     */
    protected function ensureFrankenPhpBinaryIsInstalled()
    {
        if (! is_null($frankenphpBinary = $this->findFrankenPhpBinary())) {
            return $frankenphpBinary;
        }

        if ($this->confirm('Unable to locate FrankenPHP binary. Should Octane download the binary for your operating system?', true)) {
            $this->downloadFrankenPhpBinary();

            copy(__DIR__.'/../stubs/Caddyfile', base_path('Caddyfile'));
            copy(__DIR__.'/../stubs/frankenphp-worker.php', public_path('frankenphp-worker.php'));
        }

        return base_path('frankenphp');
    }

    /**
     * Download the latest version of the FrankenPHP binary.
     *
     * @return bool
     */
    protected function downloadFrankenPhpBinary()
    {
        $arch = php_uname('m');

        $assetName = match (true) {
            PHP_OS_FAMILY === 'Linux' && $arch === 'x86_64' => 'frankenphp-linux-x86_64',
            PHP_OS_FAMILY === 'Darwin' => "frankenphp-mac-$arch",
            default => null,
        };

        if ($assetName === null) {
            $this->error('FrankenPHP binaries are currently only available for Linux (x86_64) and macOS. Other systems should use the Docker images or compile FrankenPHP manually.');

            return false;
        }

        $assets = Http::accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->get('https://api.github.com/repos/dunglas/frankenphp/releases/latest')['assets'];

        foreach ($assets as $asset) {
            if ($asset['name'] !== $assetName) {
                continue;
            }

            $path = base_path('frankenphp');

            $progressBar = null;

            (new Client)->get(
                $asset['browser_download_url'],
                [
                    'sink' => $path,
                    'progress' => function ($downloadTotal, $downloadedBytes) use (&$progressBar) {
                        if ($downloadTotal === 0) {
                            return;
                        }

                        if ($progressBar === null) {
                            $progressBar = $this->output->createProgressBar($downloadTotal);
                            $progressBar->start($downloadTotal, $downloadedBytes);

                            return;
                        }

                        $progressBar->setProgress($downloadedBytes);
                    },
                ]
            );

            chmod($path, 0755);

            $progressBar->finish();

            $this->newLine();

            return $path;
        }

        $this->error('FrankenPHP asset not found.');

        return $path;
    }

    /**
     * Ensure the FrankenPHP binary installed in your project meets Octane requirements.
     *
     * @param  string  $frakenPhpBinary
     * @return void
     */
    protected function ensureFrankenPhpBinaryMeetsRequirements($frakenPhpBinary)
    {
        $version = tap(new Process([$frakenPhpBinary, '--version'], base_path()))
            ->run()
            ->getOutput();

        $version = explode(' ', $version)[1] ?? null;

        if ($version === null) {
            return $this->warn(
                'Unable to determine the current FrankenPHP binary version. Please report this issue: https://github.com/laravel/octane/issues/new.',
            );
        }

        if (version_compare($version, $this->requiredFrankenPhpVersion, '>=')) {
            return;
        }

        $this->warn("Your FrankenPHP binary version (<fg=red>$version</>) may be incompatible with Octane.");

        if ($this->confirm('Should Octane download the latest FrankenPHP binary version for your operating system?', true)) {
            rename($frakenPhpBinary, "$frakenPhpBinary.backup");

            try {
                $this->downloadFrankenPhpBinary();
            } catch (Throwable $e) {
                report($e);

                rename("$frakenPhpBinary.backup", $frakenPhpBinary);

                return $this->warn('Unable to download FrankenPHP binary. The HTTP request exception has been logged.');
            }

            unlink("$frakenPhpBinary.backup");
        }
    }
}

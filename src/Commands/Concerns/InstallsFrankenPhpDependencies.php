<?php

namespace Laravel\Octane\Commands\Concerns;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Octane\FrankenPhp\Concerns\FindsFrankenPhpBinary;
use RuntimeException;
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
    protected $requiredFrankenPhpVersion = '1.0.2';

    /**
     * Ensure the FrankenPHP's Caddyfile and worker script are installed.
     *
     * @return void
     */
    public function ensureFrankenPhpWorkerIsInstalled()
    {
        if (! file_exists(public_path('frankenphp-worker.php'))) {
            copy(__DIR__.'/../stubs/frankenphp-worker.php', public_path('frankenphp-worker.php'));
        }
    }

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
        }

        return base_path('frankenphp');
    }

    /**
     * Download the latest version of the FrankenPHP binary.
     *
     * @return string
     */
    protected function downloadFrankenPhpBinary()
    {
        $arch = php_uname('m');

        $assetName = match (true) {
            PHP_OS_FAMILY === 'Linux' && $arch === 'x86_64' => 'frankenphp-linux-x86_64',
            PHP_OS_FAMILY === 'Linux' && $arch === 'aarch64' => 'frankenphp-linux-aarch64',
            PHP_OS_FAMILY === 'Darwin' => "frankenphp-mac-$arch",
            default => null,
        };

        if ($assetName === null) {
            throw new RuntimeException('FrankenPHP binaries are currently only available for Linux (x86_64, aarch64) and macOS. Other systems should use the Docker images or compile FrankenPHP manually.');
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

        throw new RuntimeException('FrankenPHP asset not found.');
    }

    /**
     * Ensure the installed FrankenPHP binary meets Octane's requirements.
     *
     * @param  string  $frankenPhpBinary
     * @return void
     */
    protected function ensureFrankenPhpBinaryMeetsRequirements($frankenPhpBinary)
    {
        $buildInfo = tap(new Process([$frankenPhpBinary, 'build-info'], base_path()))
            ->run()
            ->getOutput();

        $lineWithVersion = collect(explode("\n", $buildInfo))
            ->first(function ($line) {
                return str_starts_with($line, 'dep') && str_contains($line, 'github.com/dunglas/frankenphp');
            });

        if ($lineWithVersion === null) {
            return $this->warn(
                'Unable to determine the current FrankenPHP binary version. Please report this issue: https://github.com/laravel/octane/issues/new.',
            );
        }

        $version = Str::of($lineWithVersion)->trim()->afterLast('v')->value();

        if (preg_match('/\d+\.\d+\.\d+/', $version) !== 1) {
            return $this->warn(
                'Unable to determine the current FrankenPHP binary version. Please report this issue: https://github.com/laravel/octane/issues/new.',
            );
        }

        if (version_compare($version, $this->requiredFrankenPhpVersion, '>=')) {
            return;
        }

        $this->warn("Your FrankenPHP binary version (<fg=red>$version</>) may be incompatible with Octane.");

        if ($this->confirm('Should Octane download the latest FrankenPHP binary version for your operating system?', true)) {
            rename($frankenPhpBinary, "$frankenPhpBinary.backup");

            try {
                $this->downloadFrankenPhpBinary();
            } catch (Throwable $e) {
                report($e);

                rename("$frankenPhpBinary.backup", $frankenPhpBinary);

                return $this->warn('Unable to download FrankenPHP binary. The underlying error has been logged.');
            }

            unlink("$frankenPhpBinary.backup");
        }
    }
}

<?php

namespace Laravel\Octane\Commands\Concerns;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\FrankenPhp\Concerns\FindsFrankenPhpBinary;

trait InstallsFrankenPhpDependencies
{
    use FindsFrankenPhpBinary;

    /**
     * Ensure the FrankenPHP binary is installed into the project.
     */
    protected function ensureFrankenPhpBinaryIsInstalled(): string
    {
        if (! is_null($frankenphpBinary = $this->findFrankenPhpBinary())) {
            return $frankenphpBinary;
        }

        if ($this->confirm('Unable to locate FrankenPHP binary. Should Octane download the binary for your operating system?', true)) {
            $this->downloadFrankenPhpBinary();

            copy(__DIR__.'/../stubs/Caddyfile', base_path('Caddyfile'));
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
            $this->error('FrankenPHP binaries are currently only available for Linux (x86_64) and macOS. Use the Docker images or compile FrankenPHP yourself.');

            return false;
        }

        // TODO: use https://api.github.com/repos/dunglas/frankenphp/releases/latest when a stable version will be available
        $assets = Http::accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->get('https://api.github.com/repos/dunglas/frankenphp/releases', ['per_page' => 1])[0]['assets'];

        foreach ($assets as $asset) {
            if ($asset['name'] !== $assetName) {
                continue;
            }

            $path = base_path('frankenphp');

            $progressBar = null;
            (new Client())->get(
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

            if (PHP_OS_FAMILY === 'Darwin') {
                $this->warn("Run `xattr -d com.apple.quarantine $path` to release FrankenPHP from Apple's quarantine before starting the server.");
            }

            return $path;
        }

        $this->error('FrankenPHP asset not found.');

        return $path;
    }
}

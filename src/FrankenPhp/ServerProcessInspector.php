<?php

namespace Laravel\Octane\FrankenPhp;

use Illuminate\Support\Facades\Http;
use Laravel\Octane\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Symfony\Component\Process\Process;

class ServerProcessInspector implements ServerProcessInspectorContract
{
    private const ADMIN_URL = 'http://localhost:2019';

    private const FRANKENPHP_CONFIG_URL = self::ADMIN_URL.'/config/apps/frankenphp';

    public function __construct(
        protected ServerStateFile $serverStateFile,
    ) {
    }

    /**
     * Determine if the FrankenPHP server process is running.
     */
    public function serverIsRunning(): bool
    {
        try {
            return Http::get(self::FRANKENPHP_CONFIG_URL)->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Reload the FrankenPHP workers.
     */
    public function reloadServer(): void
    {
        Http::withBody(Http::get(self::FRANKENPHP_CONFIG_URL)->body(), 'application/json')
            ->withHeaders(['Cache-Control' => 'must-revalidate'])
            ->patch(self::FRANKENPHP_CONFIG_URL);
    }

    /**
     * Stop the FrankenPHP server.
     */
    public function stopServer(): bool
    {
        try {
            return Http::post(self::ADMIN_URL.'/stop')->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}

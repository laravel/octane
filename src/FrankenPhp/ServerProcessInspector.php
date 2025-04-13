<?php

namespace Laravel\Octane\FrankenPhp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Symfony\Component\Process\Process;

class ServerProcessInspector implements ServerProcessInspectorContract
{
    /**
     * Create a new server process inspector instance.
     */
    public function __construct(
        protected ServerStateFile $serverStateFile,
    ) {
    }

    /**
     * Determine if the FrankenPHP server process is running.
     */
    public function serverIsRunning(): bool
    {
        if (is_null($this->serverStateFile->read()['masterProcessId'] ?? null)) {
            return false;
        }

        try {
            return Http::get($this->adminConfigUrl())->successful();
        } catch (ConnectionException $_) {
            return false;
        }
    }

    /**
     * Reload the FrankenPHP workers.
     */
    public function reloadServer(): void
    {
        try {
            Http::withBody(Http::get($this->adminConfigUrl())->body(), 'application/json')
                ->withHeaders(['Cache-Control' => 'must-revalidate'])
                ->patch($this->adminConfigUrl());
        } catch (ConnectionException $_) {
            //
        }
    }

    /**
     * Stop the FrankenPHP server.
     */
    public function stopServer(): bool
    {
        try {
            return Http::post($this->adminUrl().'/stop')->successful();
        } catch (ConnectionException $_) {
            return false;
        }
    }

    /**
     * Get the URL to the FrankenPHP admin panel.
     */
    protected function adminUrl(): string
    {
        $serverStateFile = $this->serverStateFile->read();

        $adminHost = $serverStateFile['state']['adminHost'] ?? 'localhost';
        $adminPort = $serverStateFile['state']['adminPort'] ?? 2019;

        return "http://{$adminHost}:{$adminPort}";
    }

    /**
     * Get the URL to the FrankenPHP admin panel's configuration endpoint.
     */
    protected function adminConfigUrl(): string
    {
        return "{$this->adminUrl()}/config/apps/frankenphp";
    }
}

<?php

namespace Laravel\Octane\Tests;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Commands\StartFrankenPhpCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

class FrankenPhpBinaryDownloadTest extends TestCase
{
    protected $server;

    protected $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createApplication();

        $this->router = sys_get_temp_dir().'/octane_frankenphp_router_'.uniqid().'.php';

        file_put_contents($this->router, "<?php\necho \"#!/bin/sh\\n\";\nflush();\n");
    }

    protected function tearDown(): void
    {
        $this->server?->stop();

        @unlink($this->router);
        @unlink(base_path('frankenphp'));

        parent::tearDown();
    }

    public function test_binary_can_be_downloaded_without_content_length()
    {
        $url = $this->startServer();

        $assets = collect([
            'frankenphp-linux-x86_64',
            'frankenphp-linux-x86_64-gnu',
            'frankenphp-linux-aarch64',
            'frankenphp-linux-aarch64-gnu',
            'frankenphp-mac-x86_64',
            'frankenphp-mac-arm64',
        ])->map(fn ($name) => ['name' => $name, 'browser_download_url' => $url])->all();

        Http::fake([
            'api.github.com/*' => Http::response(['assets' => $assets]),
        ]);

        $path = (fn () => $this->downloadFrankenPhpBinary())->call($this->command());

        $this->assertSame(base_path('frankenphp'), $path);
        $this->assertSame("#!/bin/sh\n", file_get_contents($path));
    }

    protected function startServer()
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $this->server = new Process([PHP_BINARY, '-S', "127.0.0.1:$port", $this->router]);
        $this->server->start();

        $this->server->waitUntil(fn ($type, $output) => str_contains($output, 'started'));

        return "http://127.0.0.1:$port/frankenphp";
    }

    protected function command()
    {
        $command = new StartFrankenPhpCommand;

        $output = new OutputStyle(new ArrayInput([]), new BufferedOutput);

        (function () use ($output) {
            $this->output = $output;
            $this->components = new Factory($output);
        })->call($command);

        return $command;
    }
}

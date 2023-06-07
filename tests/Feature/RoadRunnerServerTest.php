<?php

namespace Laravel\Octane\Tests\Feature;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Cache\Repository;
use Laravel\Octane\OctaneServiceProvider;
use Laravel\Octane\PosixExtension;
use Laravel\Octane\RoadRunner\RoadRunnerFactory;
use Laravel\Octane\RoadRunner\ServerProcessInspector as RoadRunnerServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use Laravel\Octane\SymfonyProcessFactory;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class RoadRunnerServerTest extends TestCase
{
    /**
     * Path to the RoadRunner binary file.
     */
    private const RR_BIN_PATH = 'rr';

    private string $path;

    public function setUp(): void
    {
        $this->path = sprintf('%s/../../%s', __DIR__, rtrim(env('LARAVEL_PATH', ''), '/'));

        parent::setUp();
    }

    public function testServerStart(): void
    {
        if (env('FEATURE_TEST') !== 'rr') {
            $this->markTestSkipped('Only for RoadRunner Server');
        }

        $this->assertRoadRunnerBinaryExists();

        $rrProc = new Process([
            (new PhpExecutableFinder)->find(),
            $this->path.'/artisan',
            'octane:start',
            '--server=roadrunner',
            '--host=127.0.0.1',
            '--port=22622',
            '--workers=1',
            '--task-workers=1',
            '--log-level=debug',
        ]);

        $httpClient = new Guzzle(['base_uri' => 'http://127.0.0.1:22622']);

        // to preventing child process blocking <https://www.php.net/manual/ru/function.proc-open.php#38870>
        $rrProc->disableOutput();

        try {
            // https://symfony.com/doc/current/components/process.html#running-processes-asynchronously
            $rrProc->start();

            $this->waitUntilServerIsStarted($httpClient);
            $this->checkServerHttp($httpClient);
            $this->checkServerProcessFile();
            $this->checkCache();
        } finally {
            $this->assertSame(0, $rrProc->stop());
        }
    }

    protected function checkServerHttp(Guzzle $httpClient): void
    {
        $response = $httpClient->send(new Request('GET', '/'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Laravel', $body = (string) $response->getBody());
        $this->assertStringContainsString('https://laravel.com/', $body);

        $response = $httpClient->send(new Request('GET', '/robots.txt'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('User-agent', (string) $response->getBody());
    }

    protected function checkServerProcessFile(): void
    {
        $processInspector = new RoadRunnerServerProcessInspector(
            new RoadRunnerServerStateFile($this->path.'/storage/logs/octane-server-state.json'),
            new SymfonyProcessFactory(),
            new PosixExtension(),
        );

        $this->assertTrue($processInspector->serverIsRunning());
    }

    protected function checkCache(): void
    {
        $rpc = RoadRunnerFactory::createRPC();
        $driver = RoadRunnerFactory::createCacheStorage($rpc);
        $store = RoadRunnerFactory::createCacheStore($rpc);

        $repository = new Repository($store);
        $this->assertTrue($repository->add('k', 'v', 3600));
        $this->assertFalse($repository->add('k', 'v', 3600));
        $this->assertGreaterThan(3500, $driver->getTtl('k'));
    }

    protected function assertRoadRunnerBinaryExists(): void
    {
        $process = new Process([sprintf('%s/%s', $this->path, self::RR_BIN_PATH), '--help']);

        $this->assertSame(0, $process->run(), 'RoadRunner binary file was not found: '.$process->getOutput());

        $this->assertStringContainsString('serve', $process->getOutput());
        $this->assertStringContainsString('RoadRunner', $process->getOutput());
    }

    protected function waitUntilServerIsStarted(Guzzle $guzzle, int $limit = 100): void
    {
        for ($i = 0; $i < $limit; $i++) {
            try {
                $guzzle->send(new Request('HEAD', '/'));

                return;
            } catch (GuzzleException) {
                \usleep(30_000);
            }
        }

        $this->fail('Server was not started');
    }

    protected function getPackageProviders($app): array
    {
        return [
            OctaneServiceProvider::class,
        ];
    }
}

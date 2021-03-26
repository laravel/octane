<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Octane;
use Laravel\Octane\OctaneServiceProvider;
use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function createOctaneContext(array $requests)
    {
        $appFactory = Mockery::mock(ApplicationFactory::class);

        $appFactory->shouldReceive('createApplication')->andReturn($app = $this->createApplication());

        $app->register(new OctaneServiceProvider($app));

        $worker = new Fakes\FakeWorker($appFactory, $roadRunnerClient = new Fakes\FakeClient($requests));
        $app->bind(Client::class, fn () => $roadRunnerClient);

        $worker->boot();

        return [$app, $worker, $roadRunnerClient];
    }

    protected function createApplication()
    {
        $factory = new ApplicationFactory(realpath(__DIR__.'/../vendor/orchestra/testbench-core/laravel'));

        $app = $this->appFactory()->createApplication();

        $factory->warm($app, Octane::defaultServicesToWarm());

        return $app;
    }

    protected function appFactory()
    {
        return new ApplicationFactory(realpath(__DIR__.'/../vendor/orchestra/testbench-core/laravel'));
    }

    protected function config()
    {
        return require __DIR__.'/../config/octane.php';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Mockery::close();
    }
}

<?php

namespace Laravel\Octane\Testing;

use Illuminate\Foundation\Application;
use Illuminate\Testing\TestResponse;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Octane;
use Laravel\Octane\OctaneServiceProvider;
use Mockery;

class OctaneRequest
{
    public ApplicationFactory $factory;

    public function __construct(public Application $app)
    {
        $this->factory = new ApplicationFactory($app->basePath());

        $this->factory->warm($app, Octane::defaultServicesToWarm());
    }

    /**
     * Create Octane Request handler from Application.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return static
     */
    public static function from(Application $app): static
    {
        return new static($app);
    }

    /**
     * Handle requests using Octane.
     *
     * @param  array  $requests
     * @return array
     */
    public function handle(array $requests): array
    {
        $appFactory = Mockery::mock(ApplicationFactory::class);

        $appFactory->shouldReceive('createApplication')->andReturn($app = $this->app);

        $app->register(new OctaneServiceProvider($app));

        $worker = new Fakes\FakeWorker($appFactory, $roadRunnerClient = new Fakes\FakeClient($requests));
        $app->bind(Client::class, fn () => $roadRunnerClient);

        $worker->boot();

        $worker->run();

        return collect($roadRunnerClient->responses)
                    ->transform(fn ($response) => new TestResponse($response))
                    ->all();
    }
}

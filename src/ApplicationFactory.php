<?php

namespace Laravel\Octane;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use ReflectionObject;
use RuntimeException;

class ApplicationFactory
{
    public function __construct(protected string $basePath)
    {
    }

    /**
     * Create a new application instance.
     */
    public function createApplication(array $initialInstances = []): Application
    {
        $paths = [
            $this->basePath.'/.laravel/app.php',
            $this->basePath.'/bootstrap/app.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $this->warm($this->bootstrap(require $path, $initialInstances));
            }
        }

        throw new RuntimeException("Application bootstrap file not found in 'bootstrap' or '.laravel' directory.");
    }

    /**
     * Bootstrap the given application.
     */
    public function bootstrap(Application $app, array $initialInstances = []): Application
    {
        foreach ($initialInstances as $key => $value) {
            $app->instance($key, $value);
        }

        $app->bootstrapWith($this->getBootstrappers($app));

        $app->loadDeferredProviders();

        return $app;
    }

    /**
     * Get the application's HTTP kernel bootstrappers.
     */
    protected function getBootstrappers(Application $app): array
    {
        $method = (new ReflectionObject(
            $kernel = $app->make(HttpKernelContract::class)
        ))->getMethod('bootstrappers');

        $method->setAccessible(true);

        return $this->injectBootstrapperBefore(
            RegisterProviders::class,
            SetRequestForConsole::class,
            $method->invoke($kernel)
        );
    }

    /**
     * Inject a given bootstrapper before another bootstrapper.
     */
    protected function injectBootstrapperBefore(string $before, string $inject, array $bootstrappers): array
    {
        $injectIndex = array_search($before, $bootstrappers, true);

        if ($injectIndex !== false) {
            array_splice($bootstrappers, $injectIndex, 0, [$inject]);
        }

        return $bootstrappers;
    }

    /**
     * Warm the application with pre-resolved, cached services that persist across requests.
     */
    public function warm(Application $app, array $services = []): Application
    {
        foreach ($services ?: $app->make('config')->get('octane.warm', []) as $service) {
            if (is_string($service) && $app->bound($service)) {
                $app->make($service);
            }
        }

        return $app;
    }
}

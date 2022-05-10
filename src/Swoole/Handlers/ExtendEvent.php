<?php

namespace Laravel\Octane\Swoole\Handlers;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\DispatchesEvents;
use Laravel\Octane\Events\MainServerStarting;
use Laravel\Octane\Swoole\Event;
use Laravel\Octane\Swoole\WorkerState;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Http\Server;

class ExtendEvent
{
    use DispatchesEvents;

    /**
     * @var Application
     */
    protected $app;

    public function __construct(
        protected string $basePath,
        protected array $serverState,
        protected WorkerState $workerState,
    ) {
    }

    /**
     * Extended Swoole Event.
     *
     * @param Server $server
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(Server $server): void
    {
        $this->app = $app = $this->getApplication($server);

        $config = $this->serverState['octaneConfig'];
        $callbacks = $config['callbacks'] ?? [];

        $this->registerSwooleEvents($server, $callbacks);

        $this->dispatchEvent($app, new MainServerStarting($server));
    }

    /**
     * Get a new Laravel application instance.
     *
     * @param Server $server
     * @return Application
     */
    protected function getApplication(Server $server): Application
    {
        $initialInstances = [
            'octane.cacheTable' => $this->workerState->cacheTable,
            Server::class       => $server,
            WorkerState::class  => $this->workerState,
        ];
        $appFactory = new ApplicationFactory($this->basePath);
        return $appFactory->createApplication($initialInstances);
    }

    /**
     * Register for swoole events.
     * This will overwrite the previously registered swoole event.
     *
     * @param Server $server
     * @param array  $events
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function registerSwooleEvents(Server $server, array $events): void
    {
        foreach ($events as $event => $callback) {
            if (!Event::isSwooleEvent($event)) {
                continue;
            }

            if (is_array($callback)) {
                [$className, $method] = $callback;
                $class = $this->app->get($className);
                $callback = [$class, $method];
            }
            $server->on($event, $callback);
        }
    }
}

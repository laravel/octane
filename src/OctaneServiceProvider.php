<?php

namespace Laravel\Octane;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Cache\OctaneArrayStore;
use Laravel\Octane\Cache\OctaneStore;
use Laravel\Octane\Contracts\DispatchesCoroutines;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Exceptions\DdException;
use Laravel\Octane\Exceptions\TaskException;
use Laravel\Octane\Exceptions\TaskTimeoutException;
use Laravel\Octane\Facades\Octane as OctaneFacade;
use Laravel\Octane\RoadRunner\ServerProcessInspector as RoadRunnerServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use Laravel\Octane\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use Laravel\Octane\Swoole\ServerStateFile as SwooleServerStateFile;
use Laravel\Octane\Swoole\SignalDispatcher;
use Laravel\Octane\Swoole\SwooleCoroutineDispatcher;
use Laravel\Octane\Swoole\SwooleTaskDispatcher;

class OctaneServiceProvider extends ServiceProvider
{
    /**
     * Register Octane's services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/octane.php', 'octane');

        $this->bindListeners();

        $this->app->singleton('octane', Octane::class);

        $this->app->bind(RoadRunnerServerProcessInspector::class, function ($app) {
            return new RoadRunnerServerProcessInspector(
                $app->make(RoadRunnerServerStateFile::class),
                new SymfonyProcessFactory,
                new PosixExtension,
            );
        });

        $this->app->bind(RoadRunnerServerStateFile::class, function ($app) {
            return new RoadRunnerServerStateFile($app['config']->get(
                'octane.state_file',
                storage_path('logs/octane-server-state.json')
            ));
        });

        $this->app->bind(SwooleServerProcessInspector::class, function ($app) {
            return new SwooleServerProcessInspector(
                $app->make(SignalDispatcher::class),
                $app->make(SwooleServerStateFile::class),
                $app->make(Exec::class),
            );
        });

        $this->app->bind(SwooleServerStateFile::class, function ($app) {
            return new SwooleServerStateFile($app['config']->get(
                'octane.state_file',
                storage_path('logs/octane-server-state.json')
            ));
        });

        $this->app->bind(DispatchesCoroutines::class, function ($app) {
            return class_exists('Swoole\Http\Server')
                        ? new SwooleCoroutineDispatcher($app->bound('Swoole\Http\Server'))
                        : $app->make(SequentialCoroutineDispatcher::class);
        });
    }

    /**
     * Bootstrap Octane's services.
     *
     * @return void
     */
    public function boot()
    {
        $dispatcher = $this->app[Dispatcher::class];

        foreach ($this->app['config']->get('octane.listeners', []) as $event => $listeners) {
            foreach (array_filter(array_unique($listeners)) as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }

        $this->registerCacheDriver();
        $this->registerCommands();
        $this->registerHttpTaskHandlingRoutes();
        $this->registerPublishing();
    }

    /**
     * Bind the Octane event listeners in the container.
     *
     * @return void
     */
    protected function bindListeners()
    {
        $this->app->singleton(Listeners\CollectGarbage::class);
        $this->app->singleton(Listeners\CreateConfigurationSandbox::class);
        $this->app->singleton(Listeners\DisconnectFromDatabases::class);
        $this->app->singleton(Listeners\EnforceRequestScheme::class);
        $this->app->singleton(Listeners\EnsureRequestServerPortMatchesScheme::class);
        $this->app->singleton(Listeners\EnsureUploadedFilesAreValid::class);
        $this->app->singleton(Listeners\EnsureUploadedFilesCanBeMoved::class);
        $this->app->singleton(Listeners\FlushAuthenticationState::class);
        $this->app->singleton(Listeners\FlushQueuedCookies::class);
        $this->app->singleton(Listeners\FlushSessionState::class);
        $this->app->singleton(Listeners\FlushTemporaryContainerInstances::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToAuthorizationGate::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToBroadcastManager::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToHttpKernel::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToMailManager::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToNotificationChannelManager::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToPipelineHub::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToQueueManager::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToRouter::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToValidationFactory::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToViewFactory::class);
        $this->app->singleton(Listeners\GiveNewRequestInstanceToApplication::class);
        $this->app->singleton(Listeners\GiveNewRequestInstanceToPaginator::class);
        $this->app->singleton(Listeners\PrepareInertiaForNextOperation::class);
        $this->app->singleton(Listeners\PrepareLivewireForNextOperation::class);
        $this->app->singleton(Listeners\PrepareScoutForNextOperation::class);
        $this->app->singleton(Listeners\PrepareSocialiteForNextOperation::class);
        $this->app->singleton(Listeners\ReportException::class);
        $this->app->singleton(Listeners\StopWorkerIfNecessary::class);
    }

    /**
     * Register the Octane cache driver.
     *
     * @return void
     */
    protected function registerCacheDriver()
    {
        if (empty($this->app['config']['octane.cache'])) {
            return;
        }

        $store = $this->app->bound('octane.cacheTable')
                        ? new OctaneStore($this->app['octane.cacheTable'])
                        : new OctaneArrayStore;

        Event::listen(TickReceived::class, fn () => $store->refreshIntervalCaches());

        Cache::extend('octane', fn () => Cache::repository($store));
    }

    /**
     * Register the commands offered by Octane.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\InstallCommand::class,
                Commands\StartCommand::class,
                Commands\StartRoadRunnerCommand::class,
                Commands\StartSwooleCommand::class,
                Commands\ReloadCommand::class,
                Commands\StatusCommand::class,
                Commands\StopCommand::class,
            ]);
        }
    }

    /**
     * Register the Octane routes that handle tasks from invokers not in a Server context.
     *
     * @return void
     */
    protected function registerHttpTaskHandlingRoutes()
    {
        OctaneFacade::route('POST', '/octane/resolve-tasks', function (Request $request) {
            try {
                return new Response(serialize((new SwooleTaskDispatcher)->resolve(
                    unserialize(Crypt::decryptString($request->input('tasks'))),
                    $request->input('wait')
                )), 200);
            } catch (DecryptException) {
                return new Response('', 403);
            } catch (TaskException|DdException) {
                return new Response('', 500);
            } catch (TaskTimeoutException) {
                return new Response('', 504);
            }
        });

        OctaneFacade::route('POST', '/octane/dispatch-tasks', function (Request $request) {
            try {
                (new SwooleTaskDispatcher)->dispatch(
                    unserialize(Crypt::decryptString($request->input('tasks'))),
                );
            } catch (DecryptException) {
                return new Response('', 403);
            }

            return new Response('', 200);
        });
    }

    /**
     * Register Octane's publishing.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/octane.php' => config_path('octane.php'),
            ], 'octane-config');
        }
    }
}

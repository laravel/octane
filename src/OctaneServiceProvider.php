<?php

namespace Laravel\Octane;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Laravel\Octane\Cache\OctaneStore;
use Laravel\Octane\Commands\ReloadCommand;
use Laravel\Octane\Commands\StartCommand;
use Laravel\Octane\Commands\StartRoadRunnerCommand;
use Laravel\Octane\Commands\StartSwooleCommand;
use Laravel\Octane\Commands\StopCommand;
use Laravel\Octane\Contracts\DispatchesCoroutines;
use Laravel\Octane\Facades\Octane as OctaneFacade;
use Laravel\Octane\RoadRunner\ServerProcessInspector as RoadRunnerServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use Laravel\Octane\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use Laravel\Octane\Swoole\ServerStateFile as SwooleServerStateFile;
use Laravel\Octane\Swoole\SignalDispatcher;
use Laravel\Octane\Swoole\SwooleCoroutineDispatcher;
use Laravel\Octane\Swoole\SwooleTaskDispatcher;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OctaneServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the Laravel Octane package.
     *
     * @param  \Spatie\LaravelPackageTools\Package  $package
     * @return void
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('octane')
            ->hasConfigFile()
            ->hasCommands(
                StartCommand::class,
                StartRoadRunnerCommand::class,
                StartSwooleCommand::class,
                ReloadCommand::class,
                StopCommand::class,
            );
    }

    /**
     * Handle the package register process.
     *
     * @return void
     */
    public function packageRegistered()
    {
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
            );
        });

        $this->app->bind(SwooleServerStateFile::class, function ($app) {
            return new SwooleServerStateFile($app['config']->get(
                'octane.state_file',
                storage_path('logs/octane-server-state.json')
            ));
        });

        $this->app->bind(
            DispatchesCoroutines::class,
            class_exists('Swoole\Http\Server')
                        ? SwooleCoroutineDispatcher::class
                        : SequentialCoroutineDispatcher::class
        );
    }

    /**
     * Bind the Octane event listeners in the container.
     *
     * @return void
     */
    protected function bindListeners()
    {
        $this->app->singleton(Listeners\CollecGarbage::class);
        $this->app->singleton(Listeners\CreateConfigurationSandbox::class);
        $this->app->singleton(Listeners\DisconnectFromDatabase::class);
        $this->app->singleton(Listeners\EnforceRequestScheme::class);
        $this->app->singleton(Listeners\EnsureRequestServerPortMatchesScheme::class);
        $this->app->singleton(Listeners\EnsureUploadedFilesAreValid::class);
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
        $this->app->singleton(Listeners\StopWorkerIfNecessary::class);
        $this->app->singleton(Listeners\WriteExceptionToStderr::class);
    }

    /**
     * Handle the package boot process.
     *
     * @return void
     */
    public function packageBooted()
    {
        $dispatcher = $this->app[Dispatcher::class];

        foreach ($this->app['config']->get('octane.listeners', []) as $event => $listeners) {
            foreach (array_filter(array_unique($listeners)) as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }

        $this->registerHttpTaskHandlingRoutes();

        Cache::extend('octane', fn () => Cache::repository(
            new OctaneStore(app('octane.cacheTable'))
        ));
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
            } catch (DecryptException $e) {
                return new Response('', 403);
            }
        });

        OctaneFacade::route('POST', '/octane/dispatch-tasks', function (Request $request) {
            try {
                (new SwooleTaskDispatcher)->dispatch(
                    unserialize(Crypt::decryptString($request->input('tasks'))),
                );
            } catch (DecryptException $e) {
                return new Response('', 403);
            }

            return new Response('', 200);
        });
    }
}

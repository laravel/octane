<?php

namespace Laravel\Octane;

use Carbon\Carbon;
use Carbon\Laravel\ServiceProvider as CarbonServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\NamespacedItemResolver;
use Illuminate\Support\Str;
use Inertia\ResponseFactory;
use Laravel\Scout\EngineManager;
use Laravel\Socialite\Contracts\Factory;
use Livewire\LivewireManager;
use Monolog\ResettableInterface;

// This class resets the application state efficiently between requests.
// By accessing the initial instances directly we are reducing the overhead
// coming from Application::make() and Container::make().
class ApplicationResetter
{
    public Kernel $kernel;

    private Application $sandbox;

    private ApplicationSnapshot $snapshot;

    private Repository $config;

    private $url;

    private $octaneHttps;

    private $initialAppLocale;

    private $initialAppFallbackLocale;

    private $logDefaultDriver = null;

    private $arrayCache = null;

    public function __construct(ApplicationSnapshot $snapshot, Application $sandbox)
    {
        $this->sandbox = $sandbox;
        $this->snapshot = $snapshot;
        $this->config = $snapshot['config'];
        $this->url = $snapshot['url'];
        $this->octaneHttps = $this->config->get('octane.https');
        $this->initialAppLocale = $this->config->get('app.locale');
        $this->initialAppFallbackLocale = $this->config->get('app.fallback_locale');
        $this->kernel = $this->snapshot->make(Kernel::class);

        if ($this->config->get('cache.stores.array')) {
            $this->arrayCache = $this->snapshot->make('cache')->store('array');
        }

        if ($snapshot->resolved('log')) {
            $this->logDefaultDriver = $snapshot->make('log')->driver();
        }

        if ($snapshot->resolved('auth.driver')) {
            $snapshot->forgetInstance('auth.driver');
        }
    }

    public function prepareApplicationForNextRequest(Request $request): void
    {
        $this->flushLocaleState();
        $this->flushCookies();
        $this->flushSession();
        $this->flushAuthenticationState();
        $this->enforceRequestScheme($request);
        $this->ensureRequestServerPortMatchesScheme($request);
        $this->giveNewRequestInstanceToApplication($request);
    }

    public function prepareApplicationForNextOperation(): void
    {
        $this->createConfigurationSandbox();
        $this->createUrlGeneratorSandbox();
        $this->forgetMailers();
        $this->forgetNotificationChannelDrivers();
        $this->flushDatabaseState();
        $this->flushLogContext();
        $this->flushMonologState();
        $this->flushArrayCache();
        Str::flushCache();
        $this->flushTranslatorCache();
        $this->forgetViewEngines();

        // First-Party Packages...
        $this->prepareInertiaForNextOperation();
        $this->prepareLivewireForNextOperation();
        $this->prepareScoutForNextOperation();
        $this->prepareSocialiteForNextOperation();
    }

    private function flushLocaleState(): void
    {
        // resetting the locale is an expensive operation
        // only do it if the locale has changed
        if (Carbon::getLocale() !== $this->initialAppLocale) {
            (new CarbonServiceProvider($this->sandbox))->updateLocale();
        }
    }

    private function flushCookies()
    {
        $this->snapshot->resetInitialInstance('cookie', function ($cookie) {
            $cookie->flushQueuedCookies();
        });
    }

    private function flushSession(): void
    {
        $this->snapshot->resetInitialInstance('session', function ($session) {
            $driver = $session->driver();
            $driver->flush();
            $driver->regenerate();
        });
    }

    private function flushAuthenticationState(): void
    {
        $this->snapshot->resetInitialInstance('auth', function ($auth) {
            $auth->forgetGuards();
        });
    }

    private function enforceRequestScheme(Request $request): void
    {
        if (! $this->octaneHttps) {
            return;
        }

        $this->snapshot->resetInitialInstance('url', function ($url) use ($request) {
            $url->forceScheme('https');
            $request->server->set('HTTPS', 'on');
        });
    }

    private function ensureRequestServerPortMatchesScheme(Request $request): void
    {
        $port = $request->getPort();

        if (is_null($port) || $port === '') {
            $request->server->set(
                'SERVER_PORT',
                $request->getScheme() === 'https' ? 443 : 80
            );
        }
    }

    private function giveNewRequestInstanceToApplication(Request $request): void
    {
        $this->sandbox->instance('request', $request);
    }

    private function createConfigurationSandbox(): void
    {
        $this->sandbox->instance('config', clone $this->config);
    }

    private function createUrlGeneratorSandbox(): void
    {
        $this->sandbox->instance('url', clone $this->url);
    }

    private function forgetMailers(): void
    {
        $this->snapshot->resetInitialInstance('mail.manager', function ($mailManager) {
            $mailManager->forgetMailers();
        });
    }

    private function forgetNotificationChannelDrivers(): void
    {
        $this->snapshot->resetInitialInstance(ChannelManager::class, function ($channelManager) {
            $channelManager->forgetDrivers();
        });
    }

    private function flushDatabaseState(): void
    {
        $this->snapshot->resetInitialInstance('db', function ($db) {
            foreach ($db->getConnections() as $connection) {
                $connection->forgetRecordModificationState();
                $connection->flushQueryLog();

                // refresh query duration handling
                if (
                    method_exists($connection, 'resetTotalQueryDuration')
                    && method_exists($connection, 'allowQueryDurationHandlersToRunAgain')
                ) {
                    $connection->resetTotalQueryDuration();
                    $connection->allowQueryDurationHandlersToRunAgain();
                }
            }
        });
    }

    private function flushLogContext(): void
    {
        $this->snapshot->resetInitialInstance('log', function ($log) {
            if (method_exists($log, 'flushSharedContext')) {
                $log->flushSharedContext();
            }

            if (method_exists($this->logDefaultDriver, 'withoutContext')) {
                $this->logDefaultDriver->withoutContext();
            }

            if (method_exists($log, 'withoutContext')) {
                $log->withoutContext();
            }
        });
    }

    private function flushArrayCache(): void
    {
        if ($this->arrayCache) {
            $this->arrayCache->flush();
        }
    }

    private function flushMonologState(): void
    {
        $this->snapshot->resetInitialInstance('log', function ($log) {
            foreach ($log->getChannels() as $channel) {
                $logger = $channel->getLogger();
                if ($logger instanceof ResettableInterface) {
                    $logger->reset();
                }
            }
        });
    }

    private function flushTranslatorCache(): void
    {
        $this->snapshot->resetInitialInstance('translator', function ($translator) {
            $translator->setLocale($this->initialAppLocale);
            $translator->setFallback($this->initialAppFallbackLocale);

            if ($translator instanceof NamespacedItemResolver) {
                $translator->flushParsedKeys();
            }
        });
    }

    private function prepareInertiaForNextOperation(): void
    {
        $this->snapshot->resetInitialInstance(ResponseFactory::class, function ($responseFactory) {
            if (method_exists($responseFactory, 'flushShared')) {
                $responseFactory->flushShared();
            }
        });
    }

    private function prepareLivewireForNextOperation(): void
    {
        $this->snapshot->resetInitialInstance(LivewireManager::class, function ($livewireManager) {
            if (method_exists($livewireManager, 'flushState')) {
                $livewireManager->flushState();
            }
        });
    }

    private function prepareScoutForNextOperation(): void
    {
        $this->snapshot->resetInitialInstance(EngineManager::class, function ($engineManager) {
            if (method_exists($engineManager, 'forgetEngines')) {
                $engineManager->forgetEngines();
            }
        });
    }

    private function prepareSocialiteForNextOperation(): void
    {
        $this->snapshot->resetInitialInstance(Factory::class, function ($socialiteFactory) {
            if (! method_exists($socialiteFactory, 'forgetDrivers')) {
                return;
            }

            $socialiteFactory->forgetDrivers();
        });
    }

    private function forgetViewEngines(): void
    {
        $this->snapshot->resetInitialInstance('view.engine.resolver', function ($resolver) {
            $resolver->forget('blade');
            $resolver->forget('php');
        });
    }
}

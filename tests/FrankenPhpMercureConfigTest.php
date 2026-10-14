<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\StartFrankenPhpCommand;

class FrankenPhpMercureConfigTest extends TestCase
{
    public function test_mercure_is_disabled_by_default()
    {
        $this->app();

        $this->assertSame('', $this->call('buildMercureConfig'));
        $this->assertSame([], $this->call('mercureEnvironmentVariables'));
    }

    public function test_hub_is_configured_from_the_mercure_broadcasting_connection()
    {
        $this->app([
            'app.url' => 'http://localhost:8000/app',
            'broadcasting.default' => 'mercure',
            'broadcasting.connections.mercure' => ['driver' => 'mercure', 'secret' => 'secret'],
        ]);

        $this->assertSame(<<<'CADDYFILE'
            mercure {
            			issuer http://localhost:8000/app {
            				publisher {
            					jwt {env.OCTANE_MERCURE_PUBLISHER_JWT_KEY} HS256
            				}
            				subscriber {
            					jwt {env.OCTANE_MERCURE_SUBSCRIBER_JWT_KEY} HS256
            				}
            			}
            			resource_identifier http://localhost:8000/.well-known/mercure
            			subscriptions
            			publish_origins http://localhost:8000
            		}
            CADDYFILE, $this->call('buildMercureConfig'));

        $this->assertSame([
            'OCTANE_MERCURE_PUBLISHER_JWT_KEY' => 'secret',
            'OCTANE_MERCURE_SUBSCRIBER_JWT_KEY' => 'secret',
        ], $this->call('mercureEnvironmentVariables'));
    }

    public function test_hub_follows_the_connection_claims_and_secrets()
    {
        $this->app([
            'app.url' => 'https://example.com',
            'broadcasting.default' => 'mercure',
            'broadcasting.connections.mercure' => [
                'driver' => 'mercure',
                'secret' => 'secret',
                'subscribe_secret' => 'subscribe-secret',
                'subscribe_algorithm' => 'HS512',
                'public_url' => 'https://example.com/hub',
                'claims' => ['iss' => 'https://issuer.example.com'],
                'client_events' => false,
                'cookie_name' => 'mercure_access_token',
            ],
        ]);

        $this->assertSame(<<<'CADDYFILE'
            mercure {
            			issuer https://issuer.example.com {
            				subscriber {
            					jwt {env.OCTANE_MERCURE_SUBSCRIBER_JWT_KEY} HS512
            				}
            			}
            			resource_identifier https://example.com/hub
            			subscriptions
            			cookie_name mercure_access_token
            		}
            CADDYFILE, $this->call('buildMercureConfig'));

        $this->assertSame([
            'OCTANE_MERCURE_PUBLISHER_JWT_KEY' => '',
            'OCTANE_MERCURE_SUBSCRIBER_JWT_KEY' => 'subscribe-secret',
        ], $this->call('mercureEnvironmentVariables'));
    }

    public function test_hub_accepts_updates_published_over_http()
    {
        $this->app([
            'app.url' => 'http://localhost:8000',
            'broadcasting.default' => 'mercure',
            'broadcasting.connections.mercure' => [
                'driver' => 'mercure',
                'url' => 'http://localhost:8000/.well-known/mercure',
                'secret' => 'secret',
                'publish_secret' => 'publish-secret',
                'publish_algorithm' => 'HS384',
            ],
        ]);

        $this->assertSame(<<<'CADDYFILE'
            mercure {
            			issuer http://localhost:8000 {
            				publisher {
            					jwt {env.OCTANE_MERCURE_PUBLISHER_JWT_KEY} HS384
            				}
            				subscriber {
            					jwt {env.OCTANE_MERCURE_SUBSCRIBER_JWT_KEY} HS256
            				}
            			}
            			resource_identifier http://localhost:8000/.well-known/mercure
            			subscriptions
            			publish_origins http://localhost:8000
            		}
            CADDYFILE, $this->call('buildMercureConfig'));

        $this->assertSame([
            'OCTANE_MERCURE_PUBLISHER_JWT_KEY' => 'publish-secret',
            'OCTANE_MERCURE_SUBSCRIBER_JWT_KEY' => 'secret',
        ], $this->call('mercureEnvironmentVariables'));
    }

    public function test_hubs_on_another_origin_are_ignored()
    {
        $this->app([
            'app.url' => 'http://localhost:8000',
            'broadcasting.default' => 'mercure',
            'broadcasting.connections.mercure' => ['driver' => 'mercure', 'url' => 'https://hub.example.com/.well-known/mercure', 'secret' => 'secret'],
        ]);

        $this->assertSame('', $this->call('buildMercureConfig'));
        $this->assertSame([], $this->call('mercureEnvironmentVariables'));
    }

    public function test_octane_mercure_configuration_takes_precedence()
    {
        $this->app([
            'broadcasting.default' => 'mercure',
            'broadcasting.connections.mercure' => ['driver' => 'mercure', 'secret' => 'secret'],
            'octane.mercure' => [
                'issuer https://example.com' => [
                    'subscriber' => ['jwt' => '{env.MERCURE_SUBSCRIBER_JWT_KEY}'],
                ],
                'anonymous' => true,
                'subscriptions' => false,
                'cors_origins' => 'https://a.example.com https://b.example.com',
            ],
        ]);

        $this->assertSame(<<<'CADDYFILE'
            mercure {
            			issuer https://example.com {
            				subscriber {
            					jwt {env.MERCURE_SUBSCRIBER_JWT_KEY}
            				}
            			}
            			anonymous
            			cors_origins https://a.example.com https://b.example.com
            		}
            CADDYFILE, $this->call('buildMercureConfig'));

        $this->assertSame([], $this->call('mercureEnvironmentVariables'));
    }

    public function test_octane_mercure_configuration_can_disable_the_hub()
    {
        $this->app([
            'broadcasting.default' => 'mercure',
            'broadcasting.connections.mercure' => ['driver' => 'mercure', 'secret' => 'secret'],
            'octane.mercure' => false,
        ]);

        $this->assertSame('', $this->call('buildMercureConfig'));
        $this->assertSame([], $this->call('mercureEnvironmentVariables'));
    }

    protected function app(array $config = [])
    {
        $app = $this->createApplication();

        $app['config']->set($config);

        return $app;
    }

    protected function call(string $method)
    {
        return (fn () => $this->{$method}())->call(new StartFrankenPhpCommand);
    }
}

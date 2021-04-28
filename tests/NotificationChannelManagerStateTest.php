<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;

class NotificationChannelManagerStateTest extends TestCase
{
    public function test_notification_channel_manager_has_fresh_application_instance()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app[ChannelManager::class];

        $app['router']->get('/first', function (Application $app) {
            return spl_object_hash($app[ChannelManager::class]->getContainer());
        });

        $worker->run();

        $this->assertNotEquals($client->responses[0]->original, $client->responses[1]->original);
    }
}

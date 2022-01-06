<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use ReflectionProperty;

class FilesystemManagerStateTest extends TestCase
{
    public function test_filesystem_manager_has_fresh_application_instance()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $filesystemManagerApplication = new ReflectionProperty($app['filesystem'], 'app');
        $filesystemManagerApplication->setAccessible(true);

        $app['router']->get('/first', function (Application $app) use ($filesystemManagerApplication) {
            return spl_object_hash($filesystemManagerApplication->getValue($app['filesystem']));
        });

        $worker->run();

        $this->assertNotEquals($client->responses[0]->original, $client->responses[1]->original);
    }
}

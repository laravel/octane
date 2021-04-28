<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;

class ApplicationFactoryTest extends TestCase
{
    public function test_application_can_be_created()
    {
        $app = $this->createApplication();

        $this->assertInstanceOf(Application::class, $app);
    }

    public function test_services_can_be_warmed()
    {
        $app = $this->createApplication();
        $this->appFactory()->warm($app, $this->config()['warm']);

        $this->assertTrue($app->resolved('hash'));
    }
}

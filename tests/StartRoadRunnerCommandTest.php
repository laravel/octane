<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\StartRoadRunnerCommand;

class StartRoadRunnerCommandTest extends TestCase
{
    public function test_default_worker_command_is_resolved_relative_to_the_base_path()
    {
        $app = $this->createApplication();

        $command = $this->command();

        $this->assertEquals(
            $app->basePath('vendor/bin/roadrunner-worker'),
            $command->workerCommand()
        );
    }

    public function test_configured_relative_worker_command_is_resolved_relative_to_the_base_path()
    {
        $app = $this->createApplication();

        $app['config']->set('octane.roadrunner.command', 'vendor/bin/custom-worker');

        $command = $this->command();

        $this->assertEquals(
            $app->basePath('vendor/bin/custom-worker'),
            $command->workerCommand()
        );
    }

    public function test_configured_absolute_worker_command_is_not_prefixed_with_the_base_path()
    {
        $app = $this->createApplication();

        $app['config']->set('octane.roadrunner.command', '/var/www/current/vendor/bin/roadrunner-worker');

        $command = $this->command();

        $this->assertEquals(
            '/var/www/current/vendor/bin/roadrunner-worker',
            $command->workerCommand()
        );
    }

    protected function command()
    {
        return new class extends StartRoadRunnerCommand
        {
            public function __construct()
            {
                //
            }

            public function workerCommand()
            {
                return parent::workerCommand();
            }
        };
    }
}

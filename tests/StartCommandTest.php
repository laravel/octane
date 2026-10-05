<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\StartCommand;

class StartCommandTest extends TestCase
{
    public function test_stopping_before_the_input_is_bound_falls_back_to_the_configured_server()
    {
        $app = $this->createApplication();

        $app['config']->set('octane.server', 'frankenphp');

        $command = $this->command();
        $command->setLaravel($app);

        $command->stopServer();

        $this->assertSame([
            ['octane:stop', ['--server' => 'frankenphp']],
        ], $command->calls);
    }

    protected function command()
    {
        return new class extends StartCommand
        {
            public array $calls = [];

            public function stopServer()
            {
                parent::stopServer();
            }

            public function callSilent($command, array $arguments = [])
            {
                $this->calls[] = [$command, $arguments];

                return 0;
            }
        };
    }
}

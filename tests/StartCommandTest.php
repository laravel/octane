<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\StartCommand;
use Laravel\Octane\OctaneServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class StartCommandTest extends TestCase
{
    public function test_zero_options_are_forwarded_instead_of_falling_back_to_the_config()
    {
        $arguments = $this->runStartCommand([
            '--server' => 'swoole',
            '--task-workers' => '0',
            '--max-requests' => '0',
        ]);

        $this->assertSame('0', $arguments['--task-workers']);
        $this->assertSame('0', $arguments['--max-requests']);
    }

    public function test_omitted_options_still_fall_back_to_the_config()
    {
        $arguments = $this->runStartCommand([
            '--server' => 'swoole',
        ]);

        $this->assertSame('auto', $arguments['--task-workers']);
        $this->assertSame(500, $arguments['--max-requests']);
    }

    protected function runStartCommand(array $options): array
    {
        $app = $this->createApplication();
        $app->register(new OctaneServiceProvider($app));

        $app['config']->set('octane.task_workers', 'auto');
        $app['config']->set('octane.max_requests', 500);

        $command = new class extends StartCommand
        {
            public array $forwarded = [];

            public function call($command, array $arguments = [])
            {
                $this->forwarded = $arguments;

                return 0;
            }
        };

        $command->setLaravel($app);
        $command->run(new ArrayInput($options), new BufferedOutput);

        return $command->forwarded;
    }
}

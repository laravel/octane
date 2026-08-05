<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\StartSwooleCommand;
use Laravel\Octane\Swoole\SwooleExtension;
use Symfony\Component\Console\Input\ArrayInput;

class StartSwooleCommandTest extends TestCase
{
    public function test_default_options_leave_send_yield_to_swoole(): void
    {
        if (! defined('SWOOLE_LOG_INFO')) {
            define('SWOOLE_LOG_INFO', 0);
        }

        if (! defined('SWOOLE_LOG_ERROR')) {
            define('SWOOLE_LOG_ERROR', 3);
        }

        $this->createApplication();

        $command = new class extends StartSwooleCommand
        {
            public function defaultOptions(SwooleExtension $extension): array
            {
                return $this->defaultServerOptions($extension);
            }
        };

        $command->setInput(new ArrayInput([
            '--workers' => 1,
            '--task-workers' => 0,
            '--max-requests' => 500,
        ], $command->getDefinition()));

        $options = $command->defaultOptions(new SwooleExtension);

        $this->assertFalse($options['enable_coroutine']);
        $this->assertArrayNotHasKey('send_yield', $options);
    }
}

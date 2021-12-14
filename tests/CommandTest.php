<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CommandTest extends TestCase
{
    public function test_info()
    {
        [$output, $command] = $this->command();

        $command->info('foo');

        $this->assertEquals(<<<'EOF'

   INFO  foo

EOF, $output->fetch());
    }

    public function test_error()
    {
        [$output, $command] = $this->command();

        $command->error('bar');

        $this->assertEquals(<<<'EOF'

   ERROR  bar

EOF, $output->fetch());
    }

    public function test_warn()
    {
        [$output, $command] = $this->command();

        $command->warn('beta period');

        $this->assertEquals(<<<'EOF'

   WARN  beta period

EOF, $output->fetch());
    }

    public function test_request()
    {
        [$output, $command] = $this->command();

        $command->requestInfo([
            'method' => 'GET',
            'url' => 'http://127.0.0.1/welcome',
            'statusCode' => '200',
            'memory' => 17393560,
            'duration' => 10,
        ]);

        $command->requestInfo([
            'method' => 'POST',
            'url' => 'http://127.0.0.1:8080',
            'statusCode' => '404',
            'memory' => 20393560,
            'duration' => 1234,
        ]);

        $command->requestInfo([
            'method' => 'POST',
            'url' => 'http://127.0.0.1:8080/'.str_repeat('foo', 100),
            'statusCode' => 500,
            'memory' => 30393560,
            'duration' => 4567854,
        ]);

        $this->assertEquals(<<<'EOF'
  200    GET /welcome ......... 14.11 mb 10.00 ms
  404    POST / ............. 16.54 mb 1234.00 ms
  500    POST /foofoofo... 24.65 mb 4567854.00 ms

EOF, $output->fetch());
    }

    public function command()
    {
        $output = new BufferedOutput();

        return [$output, new class($output) extends Command
        {
            public function __construct($output)
            {
                parent::__construct('foo');

                $this->input = new ArrayInput([]);
                $this->output = $output;
            }

            protected function getTerminalWidth()
            {
                return 50;
            }
        }, ];
    }
}

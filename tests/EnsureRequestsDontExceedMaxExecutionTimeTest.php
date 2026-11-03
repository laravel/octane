<?php

namespace Laravel\Octane\Tests;

use ArrayObject;
use Laravel\Octane\Swoole\Actions\EnsureRequestsDontExceedMaxExecutionTime;
use Laravel\Octane\Swoole\SwooleExtension;
use Mockery;
use Swoole\Http\Server;

class EnsureRequestsDontExceedMaxExecutionTimeTest extends TestCase
{
    public function test_process_is_killed_if_current_request_exceeds_max_execution_time()
    {
        $table = new FakeTimerTable;

        $table['fake-worker-id'] = [
            'worker_pid' => 111,
            'time' => time() - 60,
            'fd' => 1,
        ];

        $action = new EnsureRequestsDontExceedMaxExecutionTime(
            $extension = Mockery::mock(SwooleExtension::class),
            $table,
            30,
        );

        $extension->shouldReceive('dispatchProcessSignal')->once()->with(111, SIGKILL);

        $action();
    }

    public function test_process_is_killed_even_if_client_has_disconnected()
    {
        if (! extension_loaded('openswoole') && ! extension_loaded('swoole')) {
            $this->markTestSkipped('Require openswoole/swoole extension');
        }

        $table = new FakeTimerTable;

        $table['fake-worker-id'] = [
            'worker_pid' => 111,
            'time' => time() - 60,
            'fd' => 1,
        ];

        $server = Mockery::mock(Server::class);
        $server->shouldReceive('exists')->with(1)->andReturn(false);

        $action = new EnsureRequestsDontExceedMaxExecutionTime(
            $extension = Mockery::mock(SwooleExtension::class),
            $table,
            30,
            $server,
        );

        $extension->shouldReceive('dispatchProcessSignal')->once()->with(111, SIGKILL);

        $action();

        $this->assertSame(['fake-worker-id'], $table->deleted);
    }
}

class FakeTimerTable extends ArrayObject
{
    public $deleted = [];

    public function del($workerId)
    {
        $this->deleted[] = $workerId;
    }

    public function get($workerId, $field = null)
    {
        return 1;
    }
}

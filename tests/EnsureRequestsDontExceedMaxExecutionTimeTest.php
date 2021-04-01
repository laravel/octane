<?php

namespace Laravel\Octane\Tests;

use ArrayObject;
use Laravel\Octane\Swoole\Actions\EnsureRequestsDontExceedMaxExecutionTime;
use Laravel\Octane\Swoole\SwooleExtension;
use Mockery;

class EnsureRequestsDontExceedMaxExecutionTimeTest extends TestCase
{
    /** @doesNotPerformAssertions @test */
    public function test_process_is_killed_if_current_request_exceeds_max_execution_time()
    {
        $table = new FakeTimerTable;

        $table['fake-worker-id'] = [
            'worker_pid' => 111,
            'time' => time() - 60
        ];

        $action = new EnsureRequestsDontExceedMaxExecutionTime(
            $extension = Mockery::mock(SwooleExtension::class),
            $table,
            30,
        );

        $extension->shouldReceive('dispatchProcessSignal')->once()->with(111, SIGKILL);

        $action();
    }
}

class FakeTimerTable extends ArrayObject
{
    public $deleted = [];

    public function del($workerId)
    {
        $this->deleted[] = $workerId;
    }
}

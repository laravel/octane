<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Swoole\ServerProcessInspector;
use Laravel\Octane\Swoole\ServerStateFile;
use Laravel\Octane\Swoole\SignalDispatcher;
use Mockery;

class SwooleServerProcessInspectorTest extends TestCase
{
    /** @test */
    public function test_can_determine_if_swoole_server_process_is_running_when_manager_is_running()
    {
        $inspector = new ServerProcessInspector(
            $dispatcher = Mockery::mock(SignalDispatcher::class),
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/swoole.pid')
        );

        $dispatcher->shouldReceive('canCommunicateWith')->with(2)->andReturn(true);

        $processIdFile->writeProcessIds(1, 2);

        $this->assertTrue($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    /** @test */
    public function test_can_determine_if_swoole_server_process_is_running_when_manager_cant_be_communicated_with()
    {
        $inspector = new ServerProcessInspector(
            $dispatcher = Mockery::mock(SignalDispatcher::class),
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/swoole.pid')
        );

        $dispatcher->shouldReceive('canCommunicateWith')->with(2)->andReturn(false);

        $processIdFile->writeProcessIds(1, 2);

        $this->assertFalse($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    /** @test */
    public function test_can_determine_if_swoole_server_process_is_running_when_only_master_is_running()
    {
        $inspector = new ServerProcessInspector(
            $dispatcher = Mockery::mock(SignalDispatcher::class),
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/swoole.pid')
        );

        $dispatcher->shouldReceive('canCommunicateWith')->with(1)->andReturn(true);

        $processIdFile->writeProcessIds(1, 0);

        $this->assertTrue($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    /** @test */
    public function test_can_determine_if_swoole_server_process_is_running_when_master_cant_be_communicated_with()
    {
        $inspector = new ServerProcessInspector(
            $dispatcher = Mockery::mock(SignalDispatcher::class),
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/swoole.pid')
        );

        $dispatcher->shouldReceive('canCommunicateWith')->with(1)->andReturn(false);

        $processIdFile->writeProcessIds(1, 0);

        $this->assertFalse($inspector->serverIsRunning());

        $processIdFile->delete();
    }
}

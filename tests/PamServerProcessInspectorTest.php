<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Pam\ServerProcessInspector;
use Laravel\Octane\Pam\ServerStateFile;
use Laravel\Octane\PosixExtension;
use Mockery;

class PamServerProcessInspectorTest extends TestCase
{
    public function test_can_determine_if_pam_server_process_is_running_when_master_is_running()
    {
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/pam.pid'),
            $posix = Mockery::mock(PosixExtension::class)
        );

        $posix->shouldReceive('kill')->with(1, 0)->andReturn(true);

        $processIdFile->writeProcessId(1);

        $this->assertTrue($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    public function test_can_determine_if_pam_server_process_is_running_when_master_cant_be_communicated_with()
    {
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/pam.pid'),
            $posix = Mockery::mock(PosixExtension::class)
        );

        $posix->shouldReceive('kill')->with(1, 0)->andReturn(false);

        $processIdFile->writeProcessId(1);

        $this->assertFalse($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    public function test_can_determine_if_pam_server_process_is_running_without_a_process_id()
    {
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/pam.pid'),
            $posix = Mockery::mock(PosixExtension::class)
        );

        $processIdFile->delete();

        $posix->shouldNotReceive('kill');

        $this->assertFalse($inspector->serverIsRunning());
    }

    public function test_pam_server_process_can_be_reloaded()
    {
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/pam.pid'),
            $posix = Mockery::mock(PosixExtension::class)
        );

        $posix->shouldReceive('kill')->once()->with(1, SIGHUP)->andReturn(true);

        $processIdFile->writeProcessId(1);

        $inspector->reloadServer();

        $processIdFile->delete();
    }

    public function test_pam_server_process_can_be_stopped()
    {
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/pam.pid'),
            $posix = Mockery::mock(PosixExtension::class)
        );

        $posix->shouldReceive('kill')->once()->with(1, SIGTERM)->andReturn(true);

        $processIdFile->writeProcessId(1);

        $this->assertTrue($inspector->stopServer());

        $processIdFile->delete();
    }
}

<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\PosixExtension;
use Laravel\Octane\RoadRunner\Concerns\FindsRoadRunnerBinary;
use Laravel\Octane\RoadRunner\ServerProcessInspector;
use Laravel\Octane\RoadRunner\ServerStateFile;
use Laravel\Octane\SymfonyProcessFactory;
use Mockery;

class RoadRunnerServerProcessInspectorTest extends TestCase
{
    use FindsRoadRunnerBinary;

    public function test_can_determine_if_roadrunner_server_process_is_running_when_master_is_running()
    {
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/swoole.pid'),
            new SymfonyProcessFactory,
            $posix = Mockery::mock(PosixExtension::class)
        );

        $posix->shouldReceive('kill')->with(1, 0)->andReturn(true);

        $processIdFile->writeProcessId(1);

        $this->assertTrue($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    public function test_can_determine_if_roadrunner_server_process_is_running_when_master_cant_be_communicated_with()
    {
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/swoole.pid'),
            new SymfonyProcessFactory,
            $posix = Mockery::mock(PosixExtension::class)
        );

        $posix->shouldReceive('kill')->with(1, 0)->andReturn(false);

        $processIdFile->writeProcessId(1);

        $this->assertFalse($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    /** @doesNotPerformAssertions @test */
    public function test_roadrunner_server_process_can_be_reloaded()
    {
        $this->createApplication();
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir().'/swoole.pid'),
            $processFactory = Mockery::mock(SymfonyProcessFactory::class),
            new PosixExtension
        );

        $processIdFile->writeState([
            'host' => '127.0.0.1',
            'rpcPort' => '6002',
        ]);

        $processFactory->shouldReceive('createProcess')->with(
            [$this->findRoadRunnerBinary(), 'reset', '-o', 'server=3', '-o', 'rpc.listen=tcp://127.0.0.1:6002', '-s'],
            base_path(),
        )->andReturn($process = Mockery::mock('stdClass'));

        $process->shouldReceive('start')->once()->andReturn(0);

        $process->shouldReceive('waitUntil')->once();

        $inspector->reloadServer();
    }
}

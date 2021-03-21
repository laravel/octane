<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Swoole\ServerStateFile;

class SwooleServerStateFileTest extends TestCase
{
    /** @test */
    public function test_server_state_file_can_be_managed()
    {
        $stateFile = new ServerStateFile(sys_get_temp_dir().'/swoole.json');

        $stateFile->delete();

        // Read file...
        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => null, 'managerProcessId' => null, 'state' => []], $state);

        // Write file...
        $stateFile->writeProcessIds(1, 2);
        $stateFile->writeState(['name' => 'Taylor']);
        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => 1, 'managerProcessId' => 2, 'state' => ['name' => 'Taylor']], $state);

        // Delete file...
        $stateFile->delete();
        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => null, 'managerProcessId' => null, 'state' => []], $state);
    }
}

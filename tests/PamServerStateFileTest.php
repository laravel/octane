<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Pam\ServerStateFile;

class PamServerStateFileTest extends TestCase
{
    public function test_server_state_file_can_be_managed()
    {
        $path = sys_get_temp_dir().'/pam.json';
        $stateFile = new ServerStateFile($path);

        $this->assertEquals($path, $stateFile->path());

        $stateFile->delete();

        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => null, 'state' => []], $state);

        $stateFile->writeProcessId(1);
        $stateFile->writeState(['name' => 'Taylor']);
        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => 1, 'state' => ['name' => 'Taylor']], $state);

        $stateFile->delete();
        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => null, 'state' => []], $state);
    }
}

<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\FrankenPhp\ServerStateFile;

class FrankenPhpServerStateFileTest extends TestCase
{
    public function test_server_state_file_can_be_managed()
    {
        $path = sys_get_temp_dir().'/frankenphp.json';
        $stateFile = new ServerStateFile($path);

        $this->assertEquals($path, $stateFile->path());

        $stateFile->delete();

        // Read file...
        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => null, 'state' => []], $state);

        // Write file...
        $stateFile->writeProcessId(1);
        $stateFile->writeState(['name' => 'Taylor']);
        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => 1, 'state' => ['name' => 'Taylor']], $state);

        // Delete file...
        $stateFile->delete();
        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => null, 'state' => []], $state);
    }
}

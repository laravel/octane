<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\InstallCommand;

class InstallCommandTest extends TestCase
{
    protected $environmentFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->environmentFile = sys_get_temp_dir().'/octane_install_test.env';
    }

    protected function tearDown(): void
    {
        @unlink($this->environmentFile);

        parent::tearDown();
    }

    public function test_octane_server_variable_is_updated_when_already_present()
    {
        $this->createApplication()->loadEnvironmentFrom($this->environmentFile);

        file_put_contents($this->environmentFile, "APP_NAME=Laravel\nOCTANE_SERVER=swoole\nAPP_ENV=local\n");

        (new InstallCommand)->updateEnvironmentFile('roadrunner');

        $contents = file_get_contents($this->environmentFile);

        $this->assertStringContainsString('OCTANE_SERVER=roadrunner', $contents);
        $this->assertStringNotContainsString('OCTANE_SERVER=swoole', $contents);
        $this->assertStringContainsString('APP_NAME=Laravel', $contents);
        $this->assertStringContainsString('APP_ENV=local', $contents);
    }

    public function test_octane_server_variable_is_appended_when_missing()
    {
        $this->createApplication()->loadEnvironmentFrom($this->environmentFile);

        file_put_contents($this->environmentFile, "APP_NAME=Laravel\n");

        (new InstallCommand)->updateEnvironmentFile('roadrunner');

        $this->assertStringContainsString('OCTANE_SERVER=roadrunner', file_get_contents($this->environmentFile));
    }
}

<?php

namespace Laravel\Octane\Tests;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Laravel\Octane\Commands\StartPamCommand;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class PamBinaryRequirementsTest extends TestCase
{
    protected $binDirectory;

    protected $originalPath;

    protected $originalHome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createApplication();

        $this->binDirectory = sys_get_temp_dir().'/octane_pam_bin_'.uniqid();
        mkdir($this->binDirectory);

        $this->originalPath = getenv('PATH');
        putenv('PATH='.$this->binDirectory.PATH_SEPARATOR.$this->originalPath);

        $this->originalHome = $_SERVER['HOME'] ?? null;
        $_SERVER['HOME'] = $this->binDirectory;

        Prompt::interactive(false);
    }

    protected function tearDown(): void
    {
        putenv('PATH='.$this->originalPath);

        $_SERVER['HOME'] = $this->originalHome;

        @unlink(base_path('pam'));
        @unlink($this->binDirectory.'/pam');
        @rmdir($this->binDirectory);

        parent::tearDown();
    }

    public function test_binary_on_path_is_used()
    {
        $this->fakeBinary($this->binDirectory.'/pam', '2.2.15');

        [$command, $output] = $this->command();

        $binary = (fn () => $this->ensurePamBinaryIsInstalled())->call($command);

        $this->assertSame($this->binDirectory.'/pam', $binary);

        (fn () => $this->ensurePamBinaryMeetsRequirements($binary))->call($command);

        $this->assertSame('', $output->fetch());
    }

    public function test_binary_in_project_root_is_used()
    {
        putenv('PATH='.$this->binDirectory);

        $this->fakeBinary(base_path('pam'), '2.3.0');

        [$command] = $this->command();

        $this->assertSame(base_path('pam'), (fn () => $this->ensurePamBinaryIsInstalled())->call($command));
    }

    public function test_outdated_binary_is_reported()
    {
        $this->fakeBinary($this->binDirectory.'/pam', '2.2.14');

        [$command, $output] = $this->command();

        $binary = $this->binDirectory.'/pam';

        (fn () => $this->ensurePamBinaryMeetsRequirements($binary))->call($command);

        $this->assertStringContainsString('may be incompatible with Octane', $output->fetch());
    }

    public function test_missing_binary_is_installed_with_the_official_install_script()
    {
        putenv('PATH='.$this->binDirectory);

        [$command] = $this->command();

        $this->assertSame(base_path('pam'), (fn () => $this->ensurePamBinaryIsInstalled())->call($command));
    }

    protected function command()
    {
        $command = new class extends StartPamCommand
        {
            protected function installPamBinary()
            {
                file_put_contents(base_path('pam'), "#!/bin/sh\necho 'pam 2.2.15'\n");
                chmod(base_path('pam'), 0755);
            }
        };

        $buffer = new BufferedOutput;

        $output = new OutputStyle(new ArrayInput([]), $buffer);

        (function () use ($output) {
            $this->output = $output;
            $this->components = new Factory($output);
        })->call($command);

        return [$command, $buffer];
    }

    protected function fakeBinary($path, $version)
    {
        file_put_contents($path, "#!/bin/sh\necho \"pam {$version}\"\n");
        chmod($path, 0755);
    }
}

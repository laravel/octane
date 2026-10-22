<?php

namespace Laravel\Octane\Tests;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Laravel\Octane\Commands\StartFrankenPhpCommand;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class FrankenPhpBinaryRequirementsTest extends TestCase
{
    protected $binDirectory;

    protected $originalPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createApplication();

        $this->binDirectory = sys_get_temp_dir().'/octane_frankenphp_bin_'.uniqid();
        mkdir($this->binDirectory);

        $this->originalPath = getenv('PATH');
        putenv('PATH='.$this->binDirectory.PATH_SEPARATOR.$this->originalPath);

        Prompt::interactive(false);
    }

    protected function tearDown(): void
    {
        putenv('PATH='.$this->originalPath);

        @unlink(base_path('frankenphp'));
        @unlink($this->binDirectory.'/frankenphp');
        @unlink($this->binDirectory.'/frankenphp.backup');
        @rmdir($this->binDirectory);

        parent::tearDown();
    }

    public function test_outdated_binary_on_path_is_left_untouched()
    {
        $this->fakeBinary($this->binDirectory.'/frankenphp', '1.0.0');

        $original = file_get_contents($this->binDirectory.'/frankenphp');

        [$command, $output] = $this->command();

        $binary = (fn () => $this->ensureFrankenPhpBinaryIsInstalled())->call($command);

        $this->assertSame($this->binDirectory.'/frankenphp', $binary);

        (fn () => $this->ensureFrankenPhpBinaryMeetsRequirements($binary))->call($command);

        $this->assertFileExists($binary);
        $this->assertSame($original, file_get_contents($binary));
        $this->assertFileDoesNotExist($binary.'.backup');
        $this->assertFileDoesNotExist(base_path('frankenphp'));
        $this->assertStringContainsString("Please update the FrankenPHP binary located at [$binary] manually.", $output->fetch());
    }

    public function test_outdated_binary_in_project_root_is_replaced()
    {
        putenv('PATH='.$this->binDirectory);

        $this->fakeBinary(base_path('frankenphp'), '1.0.0');

        [$command] = $this->command();

        $binary = (fn () => $this->ensureFrankenPhpBinaryIsInstalled())->call($command);

        $this->assertSame(base_path('frankenphp'), $binary);

        (fn () => $this->ensureFrankenPhpBinaryMeetsRequirements($binary))->call($command);

        $this->assertFileExists($binary);
        $this->assertSame("#!/bin/sh\n", file_get_contents($binary));
        $this->assertFileDoesNotExist($binary.'.backup');
    }

    protected function command()
    {
        $command = new class extends StartFrankenPhpCommand
        {
            protected function downloadFrankenPhpBinary()
            {
                $path = base_path('frankenphp');

                file_put_contents($path, "#!/bin/sh\n");
                chmod($path, 0755);

                return $path;
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
        file_put_contents($path, "#!/bin/sh\necho \"dep\tgithub.com/dunglas/frankenphp\tv{$version}\"\n");
        chmod($path, 0755);
    }
}

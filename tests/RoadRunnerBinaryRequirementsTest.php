<?php

namespace Laravel\Octane\Tests;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Laravel\Octane\Commands\StartRoadRunnerCommand;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RoadRunnerBinaryRequirementsTest extends TestCase
{
    protected $binDirectory;

    protected $originalPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createApplication();

        $this->binDirectory = sys_get_temp_dir().'/octane_roadrunner_bin_'.uniqid();
        mkdir($this->binDirectory);

        $this->originalPath = getenv('PATH');
        putenv('PATH='.$this->binDirectory.PATH_SEPARATOR.$this->originalPath);

        Prompt::interactive(false);
    }

    protected function tearDown(): void
    {
        putenv('PATH='.$this->originalPath);

        @unlink(base_path('rr'));
        @unlink(base_path('rr.backup'));
        @unlink($this->binDirectory.'/rr');
        @unlink($this->binDirectory.'/rr.backup');
        @rmdir($this->binDirectory);

        parent::tearDown();
    }

    public function test_outdated_binary_on_path_is_left_untouched()
    {
        $this->fakeBinary($this->binDirectory.'/rr', '2023.1.0');

        $original = file_get_contents($this->binDirectory.'/rr');

        [$command, $output] = $this->command();

        $binary = (fn () => $this->ensureRoadRunnerBinaryIsInstalled())->call($command);

        $this->assertSame($this->binDirectory.'/rr', $binary);

        (fn () => $this->ensureRoadRunnerBinaryMeetsRequirements($binary))->call($command);

        $this->assertFileExists($binary);
        $this->assertSame($original, file_get_contents($binary));
        $this->assertFileDoesNotExist($binary.'.backup');
        $this->assertFileDoesNotExist(base_path('rr'));
        $this->assertStringContainsString("Please update the RoadRunner binary located at [$binary] manually.", $output->fetch());
    }

    public function test_outdated_binary_in_project_root_is_replaced()
    {
        putenv('PATH='.$this->binDirectory);

        $this->fakeBinary(base_path('rr'), '2023.1.0');

        [$command] = $this->command();

        $binary = (fn () => $this->ensureRoadRunnerBinaryIsInstalled())->call($command);

        $this->assertSame(base_path('rr'), $binary);

        (fn () => $this->ensureRoadRunnerBinaryMeetsRequirements($binary))->call($command);

        $this->assertFileExists($binary);
        $this->assertSame("#!/bin/sh\n", file_get_contents($binary));
        $this->assertFileDoesNotExist($binary.'.backup');
    }

    protected function command()
    {
        $command = new class extends StartRoadRunnerCommand
        {
            protected function downloadRoadRunnerBinary()
            {
                $path = base_path('rr');

                file_put_contents($path, "#!/bin/sh\n");
                chmod($path, 0755);
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
        file_put_contents($path, "#!/bin/sh\necho \"rr version {$version} (build time: 2023-01-01T00:00:00+0000, go1.21.0), OS: darwin, arch: arm64\"\n");
        chmod($path, 0755);
    }
}

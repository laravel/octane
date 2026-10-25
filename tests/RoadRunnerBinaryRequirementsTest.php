<?php

namespace Laravel\Octane\Tests;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Laravel\Octane\Commands\StartRoadRunnerCommand;
use Laravel\Prompts\Prompt;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RoadRunnerBinaryRequirementsTest extends TestCase
{
    protected $binary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createApplication();

        $this->binary = sys_get_temp_dir().'/octane_roadrunner_bin_'.uniqid();

        Prompt::interactive(false);
    }

    protected function tearDown(): void
    {
        @unlink($this->binary);
        @unlink($this->binary.'.backup');

        parent::tearDown();
    }

    #[DataProvider('supportedVersions')]
    public function test_supported_binary_is_left_untouched($version)
    {
        $this->fakeBinary($this->binary, $version);

        $original = file_get_contents($this->binary);

        [$command, $output] = $this->command();

        $binary = $this->binary;

        (fn () => $this->ensureRoadRunnerBinaryMeetsRequirements($binary))->call($command);

        $this->assertFalse($command->downloaded);
        $this->assertSame($original, file_get_contents($this->binary));
        $this->assertStringNotContainsString('may be incompatible', $output->fetch());
    }

    #[DataProvider('unsupportedVersions')]
    public function test_unsupported_binary_is_replaced($version)
    {
        $this->fakeBinary($this->binary, $version);

        [$command, $output] = $this->command();

        $binary = $this->binary;

        (fn () => $this->ensureRoadRunnerBinaryMeetsRequirements($binary))->call($command);

        $this->assertTrue($command->downloaded);
        $this->assertFileDoesNotExist($this->binary.'.backup');
        $this->assertStringContainsString("Your RoadRunner binary version ($version) may be incompatible with Octane.", $output->fetch());
    }

    public static function supportedVersions()
    {
        return [
            ['2023.3.0'],
            ['2024.3.5'],
            ['2025.1.15'],
            ['3.0.0'],
            ['3.0.1'],
            ['3.1.0'],
        ];
    }

    public static function unsupportedVersions()
    {
        return [
            ['1.9.2'],
            ['2.12.3'],
            ['2023.1.0'],
            ['2023.2.2'],
        ];
    }

    protected function command()
    {
        $command = new class extends StartRoadRunnerCommand
        {
            public $downloaded = false;

            protected function downloadRoadRunnerBinary()
            {
                $this->downloaded = true;
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
        file_put_contents($path, "#!/bin/sh\necho \"rr version {$version} (build time: 2026-10-21T19:33:23+0000, go1.27.0), OS: linux, arch: amd64\"\n");
        chmod($path, 0755);
    }
}

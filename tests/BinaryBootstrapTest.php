<?php

namespace Laravel\Octane\Tests;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class BinaryBootstrapTest extends TestCase
{
    public function test_it_can_retrieve_base_path_from_environment_variable()
    {
        $basePath = realpath(__DIR__.'/../vendor/orchestra/testbench-core/laravel');

        $process = Process::fromShellCommandline(
            '"'.$this->phpBinary().'" base-path.php', __DIR__, ['APP_BASE_PATH' => $basePath], null, null
        );

        $process->mustRun();

        $output = $process->getOutput();

        if (\PHP_VERSION_ID >= 80100) {
            $output = array_filter(explode("\n", $output), function ($output) {
                return ! empty($output) && ! str_starts_with($output, 'Deprecated:');
            });

            $output = implode('', $output);
        }

        $this->assertSame($basePath, $output);
    }

    /**
     * PHP Binary path.
     */
    protected function phpBinary(): string
    {
        if (defined('PHP_BINARY')) {
            return PHP_BINARY;
        }

        return defined('PHP_BINARY') ? PHP_BINARY : (new PhpExecutableFinder())->find();
    }
}

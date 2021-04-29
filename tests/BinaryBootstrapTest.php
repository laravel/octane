<?php

namespace Laravel\Octane\Tests;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class BinaryBootstrapTest extends TestCase
{
    public function test_it_can_retrieve_base_path_from_environment_variable(): void
    {
        $basePath = realpath(__DIR__.'/../vendor/orchestra/testbench-core/laravel');

        $process = Process::fromShellCommandline(
            '"'.$this->phpBinary().'" base-path.php', __DIR__, ['APP_BASE_PATH' => $basePath], null, null
        );

        $process->mustRun();

        $this->assertSame($basePath, $process->getOutput());
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

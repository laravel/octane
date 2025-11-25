<?php

namespace Laravel\Octane\Tests;

use Orchestra\Testbench\Foundation\Actions\DeleteVendorSymlink;
use Orchestra\Testbench\Foundation\Application as Testbench;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Orchestra\Testbench\default_skeleton_path;
use function Orchestra\Testbench\package_path;
use function Orchestra\Testbench\php_binary;

class BinaryBootstrapTest extends TestCase
{
    protected $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = Testbench::createVendorSymlink(default_skeleton_path(), package_path('vendor'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new DeleteVendorSymlink)->handle($this->app);

        unset($this->app);
    }

    public function test_it_can_retrieve_base_path_from_environment_variable()
    {
        $basePath = default_skeleton_path();

        $process = Process::fromShellCommandline(
            php_binary(escape: true).' base-path.php', __DIR__, ['APP_BASE_PATH' => $basePath], null, null
        );

        $process->mustRun();

        $output = $process->getOutput();

        $output = array_filter(explode("\n", $output), function ($output) {
            return ! empty($output) && ! str_starts_with($output, 'Deprecated:');
        });

        $output = implode('', $output);

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

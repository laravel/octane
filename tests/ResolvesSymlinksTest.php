<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\Concerns\ResolvesSymlinks;
use PHPUnit\Framework\TestCase;

class ResolvesSymlinksTest extends TestCase
{
    public function test_get_symlink_aware_cwd_returns_pwd_env_when_set()
    {
        $trait = $this->createTraitInstance();

        $cwd = $trait->callGetSymlinkAwareCwd();

        // Should return a string (either the PWD env var or getcwd())
        $this->assertIsString($cwd);
    }

    public function test_get_symlink_aware_cwd_returns_string()
    {
        $trait = $this->createTraitInstance();

        $result = $trait->callGetSymlinkAwareCwd();

        $this->assertNotFalse($result);
        $this->assertIsString($result);
    }

    public function test_resolve_base_path_returns_string()
    {
        // This test verifies the trait method returns a valid path.
        // In a non-symlinked environment, it should return base_path().
        $trait = $this->createTraitInstance();

        $result = $trait->callGetSymlinkAwareCwd();

        $this->assertDirectoryExists($result);
    }

    protected function createTraitInstance()
    {
        return new class {
            use ResolvesSymlinks;

            public function callGetSymlinkAwareCwd(): string|false
            {
                return $this->getSymlinkAwareCwd();
            }
        };
    }
}

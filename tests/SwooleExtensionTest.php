<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Swoole\SwooleExtension;

class SwooleExtensionTest extends TestCase
{
    public function test_cpu_count()
    {
        $extension = new SwooleExtension();

        $cpuCount = $extension->cpuCount();

        $this->assertTrue($cpuCount > 0);
    }
}

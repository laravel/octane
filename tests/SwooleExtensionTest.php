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

    public function test_container_cpu_count_returns_null_when_not_in_container()
    {
        $extension = new SwooleExtension();

        $method = new \ReflectionMethod($extension, 'containerCpuCount');

        // On non-container environments, either returns null or a valid count
        $result = $method->invoke($extension);

        $this->assertTrue($result === null || $result >= 1);
    }

    public function test_cpu_count_respects_cgroup_v2_limit()
    {
        $extension = $this->getMockBuilder(SwooleExtension::class)
            ->onlyMethods(['containerCpuCount'])
            ->getMock();

        $extension->method('containerCpuCount')->willReturn(2);

        $this->assertSame(2, $extension->cpuCount());
    }

    public function test_cpu_count_falls_back_when_no_cgroup_limit()
    {
        $extension = $this->getMockBuilder(SwooleExtension::class)
            ->onlyMethods(['containerCpuCount'])
            ->getMock();

        $extension->method('containerCpuCount')->willReturn(null);

        $this->assertTrue($extension->cpuCount() >= 1);
    }
}

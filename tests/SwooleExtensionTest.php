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

    public function test_container_cpu_count_reads_cgroup_v2_quota()
    {
        $extension = $this->fakeExtensionWithFiles([
            '/sys/fs/cgroup/cpu.max' => '200000 100000',
        ]);

        $this->assertSame(2, $extension->readContainerCpuCount());
    }

    public function test_container_cpu_count_reads_cgroup_v1_quota()
    {
        $extension = $this->fakeExtensionWithFiles([
            '/sys/fs/cgroup/cpu/cpu.cfs_quota_us' => '250000',
            '/sys/fs/cgroup/cpu/cpu.cfs_period_us' => '100000',
        ]);

        $this->assertSame(3, $extension->readContainerCpuCount());
    }

    public function test_container_cpu_count_returns_null_when_unlimited_or_missing()
    {
        $extension = $this->fakeExtensionWithFiles([
            '/sys/fs/cgroup/cpu.max' => 'max 100000',
        ]);

        $this->assertNull($extension->readContainerCpuCount());
    }

    public function test_container_cpu_count_ignores_invalid_v2_data()
    {
        $extension = $this->fakeExtensionWithFiles([
            '/sys/fs/cgroup/cpu.max' => '200000',
        ]);

        $this->assertNull($extension->readContainerCpuCount());
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

    protected function fakeExtensionWithFiles(array $files): object
    {
        return new class($files) extends SwooleExtension
        {
            public function __construct(private array $files)
            {
                parent::__construct(
                    isReadable: fn (string $path): bool => array_key_exists($path, $this->files),
                    fileGetContents: fn (string $path): string|false => $this->files[$path] ?? false,
                );
            }

            public function readContainerCpuCount(): ?int
            {
                return $this->containerCpuCount();
            }
        };
    }
}

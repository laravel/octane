<?php

namespace Laravel\Octane\Swoole;

use Closure;
use Swoole\Process;

class SwooleExtension
{
    /**
     * Create a new Swoole extension helper.
     */
    public function __construct(
        protected ?Closure $isReadable = null,
        protected ?Closure $fileGetContents = null,
    ) {
        $this->isReadable ??= static fn (string $path): bool => is_readable($path);
        $this->fileGetContents ??= static fn (string $path): string|false => @file_get_contents($path);
    }

    /**
     * Determine if the Swoole extension is installed.
     */
    public function isInstalled(): bool
    {
        return extension_loaded('swoole') || extension_loaded('openswoole');
    }

    /**
     * Send a signal to the given process.
     */
    public function dispatchProcessSignal(int $processId, int $signal): bool
    {
        if (Process::kill($processId, 0)) {
            return Process::kill($processId, $signal);
        }

        return false;
    }

    /**
     * Set the current process name.
     */
    public function setProcessName(string $appName, string $processName): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            cli_set_process_title('swoole_http_server: '.$processName.' for '.$appName);
        }
    }

    /**
     * Get the number of CPUs detected on the system.
     */
    public function cpuCount(): int
    {
        $cgroupCpuCount = $this->containerCpuCount();

        if ($cgroupCpuCount !== null) {
            return $cgroupCpuCount;
        }

        if (function_exists('swoole_cpu_num')) {
            return swoole_cpu_num();
        }

        if (class_exists(\OpenSwoole\Util::class) && method_exists(\OpenSwoole\Util::class, 'getCPUNum')) {
            return \OpenSwoole\Util::getCPUNum();
        }

        return 1;
    }

    /**
     * Get the CPU count from the container's cgroup CPU quota, if available.
     */
    protected function containerCpuCount(): ?int
    {
        // cgroups v2...
        if (($this->isReadable)('/sys/fs/cgroup/cpu.max')) {
            $cpuMax = ($this->fileGetContents)('/sys/fs/cgroup/cpu.max');

            if ($cpuMax !== false) {
                $parts = preg_split('/\s+/', trim($cpuMax));
                $quota = $parts[0] ?? null;
                $period = isset($parts[1]) ? (int) $parts[1] : 0;

                if ($quota !== 'max' && $period > 0) {
                    return (int) max(1, ceil((int) $quota / $period));
                }
            }
        }

        // cgroups v1...
        $quotaFile = '/sys/fs/cgroup/cpu/cpu.cfs_quota_us';
        $periodFile = '/sys/fs/cgroup/cpu/cpu.cfs_period_us';

        if (($this->isReadable)($quotaFile) && ($this->isReadable)($periodFile)) {
            $quota = ($this->fileGetContents)($quotaFile);
            $period = ($this->fileGetContents)($periodFile);

            if ($quota !== false && $period !== false) {
                $quota = (int) trim($quota);
                $period = (int) trim($period);

                if ($quota > 0 && $period > 0) {
                    return (int) max(1, ceil($quota / $period));
                }
            }
        }

        return null;
    }
}

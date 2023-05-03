<?php

namespace Laravel\Octane\Swoole;

use Swoole\Process;

class SwooleExtension
{
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
        if (function_exists('swoole_cpu_num')) {
            return swoole_cpu_num();
        }

        if (class_exists(\OpenSwoole\Util::class) && method_exists(\OpenSwoole\Util::class, 'getCPUNum')) {
            return \OpenSwoole\Util::getCPUNum();
        }

        return 1;
    }
}

<?php

namespace Laravel\Octane\Swoole;

use Swoole\Process;

class SwooleExtension
{
    /**
     * Determine if the Swoole extension is installed.
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return extension_loaded('swoole');
    }

    /**
     * Send a signal to the given process.
     *
     * @param  int  $processId
     * @param  int  $signal
     * @return bool
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
     *
     * @param  string  $appName
     * @param  string  $processName
     * @return void
     */
    public function setProcessName(string $appName, string $processName): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            cli_set_process_title('swoole_http_server: '.$processName.' for '.$appName);
        }
    }

    /**
     * Get the number of CPUs detected on the system.
     *
     * @return int
     */
    public function cpuCount(): int
    {
        return swoole_cpu_num();
    }
}

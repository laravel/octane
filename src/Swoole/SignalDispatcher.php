<?php

namespace Laravel\Octane\Swoole;

use Swoole\Process;

class SignalDispatcher
{
    public function __construct(protected SwooleExtension $extension)
    {
    }

    /**
     * Determine if the given process ID can be communicated with.
     *
     * @param  int  $processId
     * @return bool
     */
    public function canCommunicateWith(int $processId): bool
    {
        return $this->signal($processId, 0);
    }

    /**
     * Send a SIGTERM signal to the given process.
     *
     * @param  int  $processId
     * @param  int  $wait
     * @return bool
     */
    public function terminate(int $processId, int $wait = 0): bool
    {
        $this->extension->dispatchProcessSignal($processId, SIGTERM);

        if ($wait) {
            $start = time();

            do {
                if ($this->canCommunicateWith($processId)) {
                    return true;
                }

                sleep(1);
            } while (time() < $start + $wait);
        }

        return false;
    }

    /**
     * Send a signal to the given process.
     *
     * @param  int  $processId
     * @param  string  $signal
     * @return bool
     */
    public function signal(int $processId, $signal): bool
    {
        return $this->extension->dispatchProcessSignal($processId, $signal);
    }
}

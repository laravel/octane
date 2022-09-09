<?php

namespace Laravel\Octane\Swoole;

use Illuminate\Support\Arr;

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
     * @param  int|array  $processId
     * @param  int  $wait
     * @return bool
     */
    public function terminate(int|array $processId, int $wait = 0): bool
    {
        $processId = Arr::wrap($processId);

        foreach ($processId as $processId) {
            $this->extension->dispatchProcessSignal($processId, SIGTERM);
        }

        if ($wait) {
            $start = time();
            $runningProcesses = $processId;

            do {
                foreach ($processId as $processId) {
                    if (! $this->canCommunicateWith($processId)) {
                        $runningProcesses = array_diff($runningProcesses, [$processId]);
                    } else {
                        $this->extension->dispatchProcessSignal($processId, SIGTERM);
                    }
                }

                if (! $runningProcesses) {
                    return true;
                }

                sleep(1);
            } while (time() < $start + $wait && $runningProcesses);
        }

        return false;
    }

    /**
     * Send a signal to the given process.
     *
     * @param  int  $processId
     * @param  int  $signal
     * @return bool
     */
    public function signal(int $processId, int $signal): bool
    {
        return $this->extension->dispatchProcessSignal($processId, $signal);
    }
}

<?php

namespace Laravel\Octane\Swoole;

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
     * @param array $processIds
     * @param  int  $wait
     *
     * @return bool
     */
    public function terminate(array $processIds, int $wait = 0): bool
    {
        foreach($processIds as $processId) {
            $this->extension->dispatchProcessSignal($processId, SIGTERM);
        }

        if ($wait) {
            $start = time();

            do {
                $allTerminated = true;

                foreach ($processIds as $processId) {
                    if ($this->canCommunicateWith($processId)) {
                        $allTerminated = false;

                        $this->extension->dispatchProcessSignal($processId, SIGTERM);
                    }
                }

                if ($allTerminated) {
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
     * @param  int  $signal
     * @return bool
     */
    public function signal(int $processId, int $signal): bool
    {
        return $this->extension->dispatchProcessSignal($processId, $signal);
    }
}

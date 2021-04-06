<?php

namespace Laravel\Octane\Swoole\Actions;

use Laravel\Octane\Swoole\SwooleExtension;

class EnsureRequestsDontExceedMaxExecutionTime
{
    public function __construct(
        protected SwooleExtension $extension,
        protected $timerTable,
        protected $maxExecutionTime
    ) {
    }

    /**
     * Invoke the action.
     *
     * @return void
     */
    public function __invoke()
    {
        foreach ($this->timerTable as $workerId => $row) {
            if ((time() - $row['time']) > $this->maxExecutionTime) {
                $this->timerTable->del($workerId);

                $this->extension->dispatchProcessSignal($row['worker_pid'], SIGKILL);
            }
        }
    }
}

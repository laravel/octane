<?php

namespace Laravel\Octane\Swoole\Actions;

use Laravel\Octane\Swoole\SwooleExtension;
use Swoole\Http\Response;
use Swoole\Http\Server;

class EnsureRequestsDontExceedMaxExecutionTime
{
    public function __construct(
        protected SwooleExtension $extension,
        protected $timerTable,
        protected $maxExecutionTime,
        protected ?Server $server = null
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

                if ($this->server instanceof Server) {
                    $response = Response::create($this->server, $row['fd']);

                    if ($response) {
                        $response->status(408);
                        $response->end();
                    }
                }
            }
        }
    }
}

<?php

namespace Laravel\Octane\Swoole;

class WorkerState
{
    public $workerId;
    public $workerPid;
    public $worker;
    public $client;
    public $cacheTable;
    public $timerTable;
    public $tables = [];
    public $tickTimerId;
    public $lastRequestTime;
}

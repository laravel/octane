<?php

namespace Laravel\Octane\Swoole;

class WorkerState
{
    public $server;

    public $workerId;

    public $workerPid;

    public $worker;

    public $client;

    public $timerTable;

    public $cacheTable;

    public $tables = [];

    public $tickTimerId;

    public $lastRequestTime;
}

<?php

namespace Laravel\Octane\Swoole;

class WorkerState
{
    public $workerId;
    public $worker;
    public $client;
    public $cacheTable;
    public $tables = [];
    public $tickTimerId;
    public $lastRequestTime;
}

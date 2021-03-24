<?php

namespace Laravel\Octane\Swoole;

class WorkerState
{
    public $workerId;
    public $worker;
    public $client;
    public $cacheTable;
    public $tickTimerId;
    public $lastRequestTime;
}

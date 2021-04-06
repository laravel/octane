<?php

namespace Laravel\Octane\Swoole;

class TaskResult
{
    public function __construct(public mixed $result)
    {
    }
}

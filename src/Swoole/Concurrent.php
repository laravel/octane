<?php

namespace Laravel\Octane\Swoole;

use Swoole\Coroutine\Channel;

class Concurrent
{
    protected Channel $channel;

    public function __construct(int $limit)
    {
        $this->channel = new Channel($limit);
    }

    public function create(callable $callback)
    {
        $this->channel->push(1);

        go(function () use ($callback) {
            $callback();

            $this->channel->pop(1);
        });
    }
}

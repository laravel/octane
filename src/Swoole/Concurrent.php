<?php

namespace Laravel\Octane\Swoole;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * @method bool isFull()
 * @method bool isEmpty()
 */
class Concurrent
{
    /**
     * @var Channel
     */
    protected $channel;

    /**
     * @var int
     */
    protected $limit;

    public function __construct(int $limit)
    {
        $this->limit = $limit;
        $this->channel = new Channel($limit);
    }

    public function __call($name, $arguments)
    {
        if (in_array($name, ['isFull', 'isEmpty'])) {
            return $this->channel->{$name}(...$arguments);
        }
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function length(): int
    {
        return $this->channel->getLength();
    }

    public function getLength(): int
    {
        return $this->channel->getLength();
    }

    public function getRunningCoroutineCount(): int
    {
        return $this->getLength();
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function create(callable $callable): void
    {
        $this->channel->push(true);

        Coroutine::create(function () use ($callable) {
            try {
                $callable();
            } catch (\Throwable $exception) {
                app(ExceptionHandler::class)->report($exception);
            } finally {
                $this->channel->pop();
            }
        });
    }
}

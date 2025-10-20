<?php

namespace Laravel\Octane\Swoole;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Carbon;
use Throwable;

class InvokeTickCallable
{
    public function __construct(
        protected string $key,
        protected $callback,
        protected int $milliseconds,
        protected bool $immediate,
        protected $cache,
        protected ExceptionHandler $exceptionHandler
    ) {
    }

    /**
     * Invoke the tick listener.
     *
     * @return void
     */
    public function __invoke()
    {
        $lastInvokedAt = $this->cache->get('tick-'.$this->key);

        if (! is_null($lastInvokedAt) &&
            (Carbon::now()->getTimestampMs() - $lastInvokedAt) < $this->milliseconds) {
            return;
        }

        $this->cache->forever('tick-'.$this->key, Carbon::now()->getTimestampMs());

        if (is_null($lastInvokedAt) && ! $this->immediate) {
            return;
        }

        try {
            call_user_func($this->callback);
        } catch (Throwable $e) {
            $this->exceptionHandler->report($e);
        }
    }

    /**
     * Indicate how often the listener should be invoked.
     *
     * @return $this
     */
    public function seconds(int $seconds)
    {
        $this->milliseconds = $seconds * 1000;

        return $this;
    }

    /**
     * Indicate how often the listener should be invoked in milliseconds.
     *
     * @return $this
     */
    public function milliseconds(int $milliseconds)
    {
        $this->milliseconds = $milliseconds;

        return $this;
    }

    /**
     * Indicate that the listener should be invoked on the first tick after the server starts.
     *
     * @return $this
     */
    public function immediate()
    {
        $this->immediate = true;

        return $this;
    }
}

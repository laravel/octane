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
        protected int $seconds,
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
            (Carbon::now()->getTimestamp() - $lastInvokedAt) < $this->seconds) {
            return;
        }

        $this->cache->forever('tick-'.$this->key, Carbon::now()->getTimestamp());

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
     * @param  int  $seconds
     * @return $this
     */
    public function seconds(int $seconds)
    {
        $this->seconds = $seconds;

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

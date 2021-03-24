<?php

namespace Laravel\Octane;

use Whoops\Exception\Inspector;

class WorkerExceptionInspector extends Inspector
{
    /**
     * Create a worker exception inspector instance.
     *
     * @param  \Throwable  $throwable
     * @param  string  $class
     * @param  array  $trace
     * @return void
     */
    public function __construct($throwable, protected $class, protected $trace)
    {
        parent::__construct($throwable);
    }

    /**
     * Get the worker exception name.
     *
     * @return string
     */
    public function getExceptionName()
    {
        return $this->class;
    }

    /**
     * Get the worker exception trace.
     *
     * @param   \Throwable  $throwable
     * @return  array
     */
    public function getTrace($throwable)
    {
        return $this->trace;
    }
}

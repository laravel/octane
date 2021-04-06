<?php

namespace Laravel\Octane\Exceptions;

use Exception;

class TaskException extends Exception
{
    /**
     * The original throwable class name.
     *
     * @var string
     */
    protected $class;

    /**
     * Creates a new task exception.
     *
     * @param  string  $class
     * @param  string  $message
     * @param  int  $code
     * @param  string  $file
     * @param  int  $line
     * @return void
     */
    public function __construct($class, $message, $code, $file, $line)
    {
        parent::__construct($message, $code);

        $this->class = $class;
        $this->file = $file;
        $this->line = $line;
    }

    /**
     * Returns the original throwable class name.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }
}

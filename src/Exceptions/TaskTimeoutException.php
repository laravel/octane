<?php

namespace Laravel\Octane\Exceptions;

use Exception;

class TaskTimeoutException extends Exception
{
    /**
     * Creates a new task timeout exception with the given milliseconds.
     *
     * @param  int  $milliseconds
     * @return static
     */
    public static function after($milliseconds)
    {
        return new static("Task timed out after $milliseconds milliseconds.");
    }
}

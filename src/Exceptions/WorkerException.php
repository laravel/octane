<?php

namespace Laravel\Octane\Exceptions;

use Exception;

class WorkerException extends Exception
{
    /**
     * Create a new worker exception instance.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  string  $file
     * @param  int  $line
     * @return void
     */
    public function __construct($message, $code, $file, $line)
    {
        $this->message = $message;
        $this->code = $code;
        $this->file = $file;
        $this->line = $line;
    }
}

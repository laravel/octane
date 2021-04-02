<?php

namespace Laravel\Octane\Exceptions;

use Exception;

class WorkerException extends Exception
{
    public function __construct(string $message, int $code, string $file, int $line)
    {
        parent::__construct($message, $code);

        $this->file = $file;
        $this->line = $line;
    }
}

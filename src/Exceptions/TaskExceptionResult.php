<?php

namespace Laravel\Octane\Exceptions;

use Throwable;

class TaskExceptionResult
{
    public function __construct(
        protected string $class,
        protected string $message,
        protected int $code,
        protected string $file,
        protected int $line,
    ) {
        //
    }

    /**
     * Creates a new task exception result from the given throwable.
     *
     * @param  \Throwable $throwable
     * @return \Laravel\Octane\Exceptions\TaskException
     */
    public static function from($throwable)
    {
        $fallbackTrace = str_starts_with($throwable->getFile(), 'closure://')
            ? collect($throwable->getTrace())->whereNotNull('file')->first()
            : null;

        return new static(
            get_class($throwable),
            $throwable->getMessage(),
            (int) $throwable->getCode(),
            $fallbackTrace['file'] ?? $throwable->getFile(),
            $fallbackTrace['line'] ?? (int) $throwable->getLine(),
        );
    }

    /**
     * Gets the original throwable.
     *
     * @return \Laravel\Octane\Exceptions\TaskException
     */
    public function getOriginal()
    {
        return new TaskException(
            $this->class,
            $this->message,
            (int) $this->code,
            $this->file,
            (int) $this->line,
        );
    }
}

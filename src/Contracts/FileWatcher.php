<?php

namespace Laravel\Octane\Contracts;

interface FileWatcher
{
    /**
     * Determine if any file changes occurred.
     *
     * @return bool
     */
    public function hasChanges(): bool;
}

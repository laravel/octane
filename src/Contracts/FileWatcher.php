<?php

namespace Laravel\Octane\Contracts;

interface FileWatcher
{
    /**
     * Determine if there are any file changes occurred
     *
     * @return bool
     */
    public function hasChanges(): bool;
}

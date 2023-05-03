<?php

namespace Laravel\Octane;

class PosixExtension
{
    /**
     * Send a signal to a given process using the POSIX extension.
     *
     * @return bool
     */
    public function kill(int $processId, int $signal)
    {
        return posix_kill($processId, $signal);
    }
}

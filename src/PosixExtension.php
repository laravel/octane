<?php

namespace Laravel\Octane;

class PosixExtension
{
    /**
     * Send a signal to a given process using the POSIX extension.
     *
     * @param  int  $processId
     * @param  int  $signal
     * @return bool
     */
    public function kill($processId, $signal)
    {
        return posix_kill($processId, $signal);
    }
}

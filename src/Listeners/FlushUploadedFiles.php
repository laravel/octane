<?php

namespace Laravel\Octane\Listeners;

use SplFileInfo;

class FlushUploadedFiles
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        foreach ($event->request->files->all() as $file) {
            if (! $file instanceof SplFileInfo ||
                ! is_string($path = $file->getRealPath())) {
                continue;
            }

            clearstatcache(true, $path);

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Support\Arr;
use SplFileInfo;

class FlushUploadedFiles
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        foreach ($event->request->files->all() as $files) {
            foreach (Arr::wrap($files) as $file) {
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
}

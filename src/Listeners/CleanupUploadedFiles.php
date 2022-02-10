<?php

declare(strict_types=1);

namespace Laravel\Octane\Listeners;

/**
 * @link https://github.com/spiral/roadrunner-laravel/issues/84
 */
class CleanupUploadedFiles
{
    /**
     * Handle the event.
     *
     * @param  mixed $event
     * @return void
     */
    public function handle($event): void
    {
        foreach ($event->request->files->all() as $file) {
            if ($file instanceof \SplFileInfo) {
                if (\is_string($path = $file->getRealPath())) {
                    \clearstatcache(true, $path);

                    if (\is_file($path)) {
                        \unlink($path);
                    }
                }
            }
        }
    }
}

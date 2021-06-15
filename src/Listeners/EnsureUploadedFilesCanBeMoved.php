<?php

namespace Laravel\Octane\Listeners;

class EnsureUploadedFilesCanBeMoved
{
    /**
     * Handle the event.
     *
     * @link https://github.com/spiral/roadrunner-laravel/issues/43
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! function_exists('\\Symfony\\Component\\HttpFoundation\\File\\move_uploaded_file')) {
            require __DIR__.'/../../fixes/fix-symfony-file-moving.php';
        }
    }
}

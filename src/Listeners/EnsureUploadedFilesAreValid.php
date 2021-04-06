<?php

namespace Laravel\Octane\Listeners;

class EnsureUploadedFilesAreValid
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! function_exists('\\Symfony\\Component\\HttpFoundation\\File\\is_uploaded_file')) {
            require __DIR__.'/../../fixes/fix-symfony-file-validation.php';
        }
    }
}

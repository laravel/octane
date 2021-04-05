<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:install';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Install the Octane components and resources';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        // Publish...
        $this->callSilent('vendor:publish', ['--tag' => 'octane-config', '--force' => true]);

        // Updates .gitignore...
        if (File::exists(base_path('.gitignore'))) {
            collect(['rr', '.rr.yaml'])
                ->each(function ($file) {
                    $contents = File::get(base_path('.gitignore'));
                    if (! Str::contains($contents, $file.PHP_EOL)) {
                        File::append(
                            base_path('.gitignore'),
                            $file.PHP_EOL
                        );
                    }
                });
        }

        $this->info('Octane installed successfully.');
    }
}

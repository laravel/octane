<?php

namespace Laravel\Octane\Commands\Concerns;

use Exception;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

trait InstallsRoadRunnerDependencies
{
    /**
     * Ensure the RoadRunner package is installed into the project.
     *
     * @return void
     */
    protected function ensureRoadRunnerPackageIsInstalled()
    {
        if (class_exists('Spiral\RoadRunner\Http\PSR7Worker')) {
            return;
        }

        if (! $this->confirm('Octane requires "spiral/roadrunner:^2.0". Do you wish to install it as a dependency?')) {
            throw new Exception('Octane requires "spiral/roadrunner".');
        }

        $command = $this->findComposer().' require spiral/roadrunner:^2.0 --with-all-dependencies';

        $process = Process::fromShellCommandline($command, null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->output->writeln('Warning: '.$e->getMessage());
            }
        }

        try {
            $process->run(function ($type, $line) {
                $this->output->write($line);
            });
        } catch (ProcessSignaledException $e) {
            if (extension_loaded('pcntl') && $e->getSignal() !== SIGINT) {
                throw $e;
            }
        }
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        $composerPath = getcwd().'/composer.phar';

        if (file_exists($composerPath)) {
            return '"'.PHP_BINARY.'" '.$composerPath;
        }

        return 'composer';
    }

    /**
     * Ensure the RoadRunner binary is installed into the project.
     *
     * @return string
     */
    protected function ensureRoadRunnerBinaryIsInstalled(): string
    {
        if (file_exists(base_path('rr'))) {
            return base_path('rr');
        }

        if (! is_null($roadRunnerBinary = (new ExecutableFinder)->find('rr', null, [base_path()]))) {
            if (! Str::contains($roadRunnerBinary, 'vendor/bin/rr')) {
                return $roadRunnerBinary;
            }
        }

        if ($this->confirm('Unable to locate RoadRunner binary. Should Octane download the binary for your operating system?', true)) {
            tap(new Process(array_filter([
                './vendor/bin/rr',
                'get-binary',
                '-n',
                '--ansi',
            ]), base_path(), null, null, null))->run(
                fn ($type, $buffer) => $this->output->write($buffer)
            );

            $this->line('');

            chmod(base_path('rr'), 755);

            copy(__DIR__.'/../stubs/rr.yaml', base_path('.rr.yaml'));
        }

        return base_path('rr');
    }
}

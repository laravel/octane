<?php

namespace Laravel\Octane\Commands\Concerns;

use Illuminate\Support\Str;
use Laravel\Octane\RoadRunner\Concerns\FindsRoadRunnerBinary;
use RuntimeException;
use Spiral\RoadRunner\Http\PSR7Worker;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

trait InstallsRoadRunnerDependencies
{
    use FindsRoadRunnerBinary;

    /**
     * The minimum required version of the RoadRunner binary.
     *
     * @var string
     */
    protected $requiredVersion = '2.8.2';

    /**
     * Determine if RoadRunner is installed.
     *
     * @return bool
     */
    protected function isRoadRunnerInstalled()
    {
        return class_exists(PSR7Worker::class);
    }

    /**
     * Ensure the RoadRunner package is installed into the project.
     *
     * @return bool
     */
    protected function ensureRoadRunnerPackageIsInstalled()
    {
        if ($this->isRoadRunnerInstalled()) {
            return true;
        }

        if (! $this->confirm('Octane requires "spiral/roadrunner:^2.8.2". Do you wish to install it as a dependency?')) {
            $this->error('Octane requires "spiral/roadrunner".');

            return false;
        }

        $command = $this->findComposer().' require spiral/roadrunner:^2.8.2 --with-all-dependencies';

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

        return true;
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        $composerPath = getcwd().'/composer.phar';

        $phpPath = (new PhpExecutableFinder)->find();

        if (! file_exists($composerPath)) {
            $composerPath = (new ExecutableFinder())->find('composer');
        }

        return '"'.$phpPath.'" '.$composerPath;
    }

    /**
     * Ensure the RoadRunner binary is installed into the project.
     *
     * @return string
     */
    protected function ensureRoadRunnerBinaryIsInstalled(): string
    {
        if (! is_null($roadRunnerBinary = $this->findRoadRunnerBinary())) {
            return $roadRunnerBinary;
        }

        if ($this->confirm('Unable to locate RoadRunner binary. Should Octane download the binary for your operating system?', true)) {
            $this->downloadRoadRunnerBinary();

            copy(__DIR__.'/../stubs/rr.yaml', base_path('.rr.yaml'));
        }

        return base_path('rr');
    }

    /**
     * Ensure the RoadRunner binary installed in your project meets Octane requirements.
     *
     * @param  string  $roadRunnerBinary
     * @return void
     */
    protected function ensureRoadRunnerBinaryMeetsRequirements($roadRunnerBinary)
    {
        $version = tap(new Process([$roadRunnerBinary, '--version'], base_path()))
            ->run()
            ->getOutput();

        if (! Str::startsWith($version, 'rr version 2.')) {
            return $this->warn(
                'Unable to determine the current RoadRunner binary version. Please report this issue: https://github.com/laravel/octane/issues/new.'
            );
        }

        $version = explode(' ', $version)[2];

        if (version_compare($version, $this->requiredVersion, '<')) {
            $this->warn("Your RoadRunner binary version (<fg=red>$version</>) may be incompatible with Octane.");

            if ($this->confirm('Should Octane download the latest RoadRunner binary version for your operating system?', true)) {
                rename($roadRunnerBinary, "$roadRunnerBinary.backup");

                try {
                    $this->downloadRoadRunnerBinary();
                } catch (Throwable $e) {
                    report($e);

                    rename("$roadRunnerBinary.backup", $roadRunnerBinary);

                    return $this->warn('Unable to download RoadRunner binary. The HTTP request exception has been logged.');
                }

                unlink("$roadRunnerBinary.backup");
            }
        }
    }

    /**
     * Download the latest version of the RoadRunner binary.
     *
     * @return void
     */
    protected function downloadRoadRunnerBinary()
    {
        tap(new Process(array_filter([
            (new PhpExecutableFinder)->find(),
            './vendor/bin/rr',
            'get-binary',
            '-n',
            '--ansi',
        ]), base_path(), null, null, null))->mustRun(
            fn ($type, $buffer) => $this->output->write($buffer)
        );

        chmod(base_path('rr'), 0755);

        $this->line('');
    }
}

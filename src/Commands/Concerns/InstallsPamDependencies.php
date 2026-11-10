<?php

namespace Laravel\Octane\Commands\Concerns;

use Laravel\Octane\Pam\Concerns\FindsPamBinary;
use RuntimeException;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;

trait InstallsPamDependencies
{
    use FindsPamBinary;

    /**
     * The minimum required version of the PAM binary.
     *
     * @var string
     */
    protected $requiredPamVersion = '2.2.14';

    /**
     * The URL of PAM's official install script.
     *
     * @var string
     */
    protected $pamInstallScriptUrl = 'https://github.com/push-in/pam/releases/latest/download/install.sh';

    /**
     * Ensure the PAM binary is installed.
     *
     * @return string
     */
    protected function ensurePamBinaryIsInstalled()
    {
        if (! is_null($pamBinary = $this->findPamBinary())) {
            return $pamBinary;
        }

        if (confirm('Unable to locate PAM binary. Should Octane install PAM using its official install script?', true)) {
            $this->installPamBinary();
        }

        if (is_null($pamBinary = $this->findPamBinary())) {
            throw new RuntimeException("Unable to locate PAM binary. Please install PAM using [curl -fsSL {$this->pamInstallScriptUrl} | sh].");
        }

        return $pamBinary;
    }

    /**
     * Install PAM using its official install script, which verifies the release checksum.
     *
     * @return void
     */
    protected function installPamBinary()
    {
        if (! in_array(PHP_OS_FAMILY, ['Linux', 'Darwin'])) {
            throw new RuntimeException('PAM binaries are only available for Linux and macOS. On Windows, use WSL or Docker.');
        }

        Process::fromShellCommandline(
            'curl --proto "=https" --tlsv1.2 -fsSL "$PAM_INSTALL_SCRIPT" | sh',
            base_path(),
            ['PAM_INSTALL_SCRIPT' => $this->pamInstallScriptUrl],
        )->setTimeout(300)->mustRun(fn ($type, $buffer) => $this->output->write($buffer));
    }

    /**
     * Ensure the installed PAM binary meets Octane's requirements.
     *
     * @param  string  $pamBinary
     * @return void
     */
    protected function ensurePamBinaryMeetsRequirements($pamBinary)
    {
        $version = tap(new Process([$pamBinary, '--version'], base_path()))
            ->run()
            ->getOutput();

        if (preg_match('/(\d+\.\d+\.\d+)/', $version, $matches) !== 1) {
            return $this->components->warn(
                'Unable to determine the current PAM binary version. Please report this issue: https://github.com/push-in/pam/issues/new.',
            );
        }

        if (version_compare($matches[1], $this->requiredPamVersion, '<')) {
            $this->components->warn("Your PAM binary version (<fg=red>{$matches[1]}</>) may be incompatible with Octane. Please update it using [pam self-update].");
        }
    }
}

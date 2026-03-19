<?php

namespace Laravel\Octane\Commands\Concerns;

trait ResolvesSymlinks
{
    /**
     * Resolve the application base path, following symlinks to detect
     * zero-downtime deployment strategies (e.g. Deployer, Envoyer).
     *
     * When the application is started via a symlinked path (e.g. /var/www/current -> /var/www/releases/v5),
     * PHP's realpath() resolves to the target directory (/var/www/releases/v5). This breaks
     * octane:reload because workers continue loading files from the old resolved path even
     * after the symlink is atomically switched to a new release.
     *
     * This method detects the original symlinked working directory (the path the user actually
     * invoked artisan from) and returns it so that workers always load files through the
     * symlink, picking up new releases after a reload.
     *
     * @return string The symlink-aware base path, or the real base path if no symlink is detected.
     */
    protected function resolveBasePath(): string
    {
        $realBasePath = base_path();

        // Detect the working directory the process was actually started from.
        // This preserves symlinked paths rather than resolving them.
        $cwd = $this->getSymlinkAwareCwd();

        if ($cwd !== false && $cwd !== $realBasePath) {
            return $cwd;
        }

        return $realBasePath;
    }

    /**
     * Get the current working directory without resolving symlinks.
     *
     * On Unix-like systems, `pwd` (without -P) returns the logical path
     * which preserves symlinks. PHP's getcwd() always resolves symlinks.
     *
     * @return string|false
     */
    protected function getSymlinkAwareCwd(): string|false
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return getcwd();
        }

        $pwd = getenv('PWD');

        if ($pwd !== false && is_dir($pwd) && realpath($pwd) === realpath(getcwd())) {
            return $pwd;
        }

        return getcwd();
    }
}

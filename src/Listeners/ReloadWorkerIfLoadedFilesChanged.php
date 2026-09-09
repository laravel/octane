<?php

namespace Laravel\Octane\Listeners;

use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Contracts\StoppableClient;
use Laravel\Octane\Exceptions\StaleApplicationException;
use Laravel\Octane\Octane;
use Swoole\Http\Server;

class ReloadWorkerIfLoadedFilesChanged
{
    /**
     * The last seen modification times of loaded application files.
     *
     * @var array<string, int|false>
     */
    protected array $fileMtimes = [];

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $this->enabled($event)) {
            return;
        }

        $changed = $this->changedLoadedFiles($event->sandbox);

        if ($changed === []) {
            return;
        }

        $this->reloadWorker($event->sandbox);

        $exception = new StaleApplicationException($changed);

        if (! $event->sandbox->environment('testing')) {
            Octane::writeError($exception->getMessage());
        }

        throw $exception;
    }

    /**
     * Determine whether stale loaded files should stop the worker.
     *
     * @param  mixed  $event
     */
    protected function enabled($event): bool
    {
        $configured = $event->sandbox['config']->get('octane.reload_changed_files');

        if (! is_null($configured)) {
            return (bool) $configured;
        }

        return $event->sandbox->isLocal();
    }

    /**
     * Get watched application files that were loaded and then changed on disk.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, string>
     */
    protected function changedLoadedFiles($app): array
    {
        clearstatcache();

        $changed = [];

        foreach ($this->watchedLoadedFiles($app) as $file) {
            $mtime = @filemtime($file);

            if (! array_key_exists($file, $this->fileMtimes)) {
                $this->fileMtimes[$file] = $mtime;

                continue;
            }

            if ($mtime !== $this->fileMtimes[$file]) {
                $changed[] = $file;
                $this->fileMtimes[$file] = $mtime;
            }
        }

        return $changed;
    }

    /**
     * Get included PHP files that fall under Octane's watch paths.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, string>
     */
    protected function watchedLoadedFiles($app): array
    {
        $prefixes = $this->watchPrefixes($app);

        if ($prefixes === []) {
            return [];
        }

        $files = [];

        foreach (get_included_files() as $file) {
            foreach ($prefixes as $prefix) {
                if ($file === $prefix || str_starts_with($file, $prefix.DIRECTORY_SEPARATOR)) {
                    $files[] = $file;

                    break;
                }
            }
        }

        return $files;
    }

    /**
     * Resolve Octane watch paths to absolute prefixes.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, string>
     */
    protected function watchPrefixes($app): array
    {
        $base = $app->basePath();
        $prefixes = [];

        foreach ($app['config']->get('octane.watch', []) as $path) {
            $relative = preg_replace('/\*.*$/', '', str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
            $relative = rtrim($relative, DIRECTORY_SEPARATOR);

            $absolute = $this->isAbsolutePath($relative)
                ? $relative
                : $base.DIRECTORY_SEPARATOR.$relative;

            $prefixes[] = realpath($absolute) ?: $absolute;
        }

        return array_values(array_unique($prefixes));
    }

    /**
     * Determine if the given path is absolute.
     */
    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }

    /**
     * Reload the current worker so the next request boots fresh PHP classes.
     *
     * @param  \Illuminate\Foundation\Application  $sandbox
     */
    protected function reloadWorker($sandbox): void
    {
        if (class_exists(Server::class) && $sandbox->bound(Server::class)) {
            $sandbox->make(Server::class)->reload();

            return;
        }

        $client = $sandbox->make(Client::class);

        if ($client instanceof StoppableClient) {
            $client->stop();
        }
    }
}

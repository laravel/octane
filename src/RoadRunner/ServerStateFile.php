<?php

namespace Laravel\Octane\RoadRunner;

use RuntimeException;

class ServerStateFile
{
    public function __construct(protected string $path)
    {
    }

    /**
     * Read the server state from the server state file.
     *
     * @return array
     */
    public function read(): array
    {
        $state = is_readable($this->path)
                    ? json_decode(file_get_contents($this->path), true)
                    : [];

        return [
            'masterProcessId' => $state['masterProcessId'] ?? null,
            'state' => $state['state'] ?? [],
        ];
    }

    /**
     * Write the given process ID to the server state file.
     *
     * @param  int  $masterProcessId
     * @return void
     */
    public function writeProcessId(int $masterProcessId): void
    {
        if (! is_writable($this->path) && ! is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to process ID file.');
        }

        file_put_contents($this->path, json_encode(
            array_merge($this->read(), ['masterProcessId' => $masterProcessId]),
            JSON_PRETTY_PRINT
        ));
    }

    /**
     * Write the given state array to the server state file.
     *
     * @param  array  $newState
     * @return void
     */
    public function writeState(array $newState): void
    {
        if (! is_writable($this->path) && ! is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to process ID file.');
        }

        file_put_contents($this->path, json_encode(
            array_merge($this->read(), ['state' => $newState]),
            JSON_PRETTY_PRINT
        ));
    }

    /**
     * Delete the process ID file.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (is_writable($this->path)) {
            return unlink($this->path);
        }

        return false;
    }

    /**
     * Get the path to the process ID file.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }
}

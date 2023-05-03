<?php

namespace Laravel\Octane;

use Exception;
use Laravel\Octane\Swoole\WorkerState;
use Swoole\Http\Server;
use Swoole\Table;
use Throwable;

class Octane
{
    use Concerns\ProvidesConcurrencySupport;
    use Concerns\ProvidesDefaultConfigurationOptions;
    use Concerns\ProvidesRouting;
    use Concerns\RegistersTickHandlers;

    /**
     * Get a Swoole table instance.
     */
    public function table(string $table): Table
    {
        if (! app()->bound(Server::class)) {
            throw new Exception('Tables may only be accessed when using the Swoole server.');
        }

        $tables = app(WorkerState::class)->tables;

        if (! isset($tables[$table])) {
            throw new Exception("Swoole table [{$table}] has not been configured.");
        }

        return $tables[$table];
    }

    /**
     * Format an exception to a string that should be returned to the client.
     */
    public static function formatExceptionForClient(Throwable $e, bool $debug = false): string
    {
        return $debug ? (string) $e : 'Internal server error.';
    }
}

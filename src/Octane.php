<?php

namespace Laravel\Octane;

use Exception;
use Laravel\Octane\Swoole\WorkerState;
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
        $serverClass = config('octane.swoole.enable_web_socket', false)
            ? \Swoole\Websocket\Server::class
            : \Swoole\Http\Server::class;

        if (! app()->bound($serverClass)) {
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

    /**
     * Write an error message to STDERR or to the SAPI logger if not in CLI mode.
     */
    public static function writeError(string $message): void
    {
        if (defined('STDERR')) {
            fwrite(STDERR, $message.PHP_EOL);

            return;
        }

        error_log($message, 4);
    }
}

<?php

$config = $serverState['octaneConfig'];

try {
    $server = new Swoole\Http\Server(
        $serverState['host'] ?? '127.0.0.1',
        $serverState['port'] ?? '8080',
        SWOOLE_PROCESS,
        SWOOLE_SOCK_TCP,
    );
} catch (Throwable $e) {
    Laravel\Octane\Stream::shutdown($e);

    exit(1);
}

$server->set(array_merge(
    $serverState['defaultServerOptions'],
    $config['swoole']['options'] ?? []
));

return $server;

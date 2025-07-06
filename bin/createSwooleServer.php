<?php

$config = $serverState['octaneConfig'];

try {
    $host = $serverState['host'] ?? '127.0.0.1';

    $sock = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? SWOOLE_SOCK_TCP : SWOOLE_SOCK_TCP6;

    $unixSock = $serverState['sock'] ?? null;
    if (is_string($unixSock) && str_starts_with($unixSock, '/')) {
        $server = new Swoole\Http\Server(
            $unixSock,  // Use UNIX socket
            0,          // Port is not needed
            $config['swoole']['mode'] ?? SWOOLE_PROCESS,
            SWOOLE_SOCK_UNIX_STREAM
        );
    } else {
        $server = new Swoole\Http\Server(
            $host,
            $serverState['port'] ?? 8000,
            $config['swoole']['mode'] ?? SWOOLE_PROCESS,
            ($config['swoole']['ssl'] ?? false)
                ? $sock | SWOOLE_SSL
                : $sock,
        );
    }
} catch (Throwable $e) {
    Laravel\Octane\Stream::shutdown($e);

    exit(1);
}

$server->set(array_merge(
    $serverState['defaultServerOptions'],
    $config['swoole']['options'] ?? []
));

return $server;

<?php

$config = $serverState['octaneConfig'];

try {
    $serverClass = ($config['swoole']['enable_web_socket'] ?? false)
        ? \Swoole\Websocket\Server::class
        : \Swoole\Http\Server::class;

    $host = $serverState['host'] ?? '127.0.0.1';

    $sock = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? SWOOLE_SOCK_TCP : SWOOLE_SOCK_TCP6;

    $server = new $serverClass(
        $host,
        $serverState['port'] ?? 8000,
        $config['swoole']['mode'] ?? SWOOLE_PROCESS,
        ($config['swoole']['ssl'] ?? false)
            ? $sock | SWOOLE_SSL
            : $sock,
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

<?php

$config = $serverState['octaneConfig'];

$server = new Swoole\Http\Server(
    $serverState['host'] ?? '127.0.0.1',
    $serverState['port'] ?? '8080',
    SWOOLE_PROCESS,
    SWOOLE_SOCK_TCP,
);

$server->set(array_merge(
    $serverState['defaultServerOptions'],
    $config['swoole']['options'] ?? []
));

return $server;

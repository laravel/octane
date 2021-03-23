<?php

use Swoole\Table;

if ($serverState['octaneConfig']['cache'] ?? false) {
    $cacheTable = new Table($serverState['octaneConfig']['cache']['rows'] ?? 1000);

    $cacheTable->column('value', Table::TYPE_STRING, $serverState['octaneConfig']['cache']['bytes'] ?? 10000);
    $cacheTable->column('expiration', Table::TYPE_INT);

    $cacheTable->create();

    return $cacheTable;
}

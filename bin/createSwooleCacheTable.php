<?php

use Swoole\Table;

if ($serverState['octaneConfig']['cache'] ?? false) {
    $table = new Table($serverState['octaneConfig']['cache']['rows'] ?? 1000);

    $table->column('value', Table::TYPE_STRING, $serverState['octaneConfig']['cache']['bytes'] ?? 10000);
    $table->column('expiration', Table::TYPE_INT);

    $table->create();

    return $table;
}

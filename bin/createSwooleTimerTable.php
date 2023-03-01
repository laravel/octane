<?php

use Laravel\Octane\Tables\TableFactory;
use Swoole\Table;

require_once __DIR__.'/../src/Tables/TableFactory.php';

if (($serverState['octaneConfig']['max_execution_time'] ?? 0) > 0) {
    $timerTable = TableFactory::make($serverState['octaneConfig']['max_timer_table_size'] ?? 250);

    $timerTable->column('worker_pid', Table::TYPE_INT);
    $timerTable->column('time', Table::TYPE_INT);
    $timerTable->column('fd', Table::TYPE_INT);

    $timerTable->create();

    return $timerTable;
}

return null;

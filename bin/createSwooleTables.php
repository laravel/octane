<?php

use Laravel\Octane\Tables\TableFactory;
use Swoole\Table;

require_once __DIR__.'/../src/Tables/TableFactory.php';

$tables = [];

foreach ($serverState['octaneConfig']['tables'] ?? [] as $name => $columns) {
    $table = TableFactory::make(explode(':', $name)[1] ?? 1000);

    foreach ($columns ?? [] as $columnName => $column) {
        $table->column($columnName, match (explode(':', $column)[0] ?? 'string') {
            'string' => Table::TYPE_STRING,
            'int' => Table::TYPE_INT,
            'float' => Table::TYPE_FLOAT,
        }, explode(':', $column)[1] ?? 1000);
    }

    $table->create();

    $tables[explode(':', $name)[0]] = $table;
}

return $tables;

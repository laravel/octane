<?php

namespace Laravel\Octane\Tables;

class TableFactory
{
    /**
     * Creates a new Swoole Table with the given size.
     *
     * @return \Swoole\Table
     */
    public static function make($size)
    {
        if (extension_loaded('openswoole')) {
            require_once __DIR__.'/OpenSwooleTable.php';

            return new OpenSwooleTable($size);
        }

        require_once __DIR__.'/SwooleTable.php';

        return new SwooleTable($size);
    }
}

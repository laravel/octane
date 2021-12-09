<?php

namespace Laravel\Octane\Tables;

use Swoole\Table;

class OpenSwooleTable extends Table
{
    use Concerns\EnsuresColumnSizes;

    /**
     * The table columns.
     *
     * @var array
     */
    protected $columns;

    /**
     * Set the data type and size of the columns.
     *
     * @param  string  $name
     * @param  int  $type
     * @param  int  $size
     * @return bool
     */
    public function column(string $name, int $type, int $size = 0): bool
    {
        $this->columns[$name] = [$type, $size];

        return parent::column($name, $type, $size);
    }

    /**
     * Update a row of the table.
     *
     * @param  string  $key
     * @param  array  $values
     * @return bool
     */
    public function set(string $key, array $values): bool
    {
        collect($values)
            ->each($this->ensureColumnsSize());

        return parent::set($key, $values);
    }
}

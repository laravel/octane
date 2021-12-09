<?php

namespace Laravel\Octane\Tables;

use Illuminate\Support\Arr;
use Laravel\Octane\Exceptions\ValueTooLargeForColumnException;
use Swoole\Table;

class SwooleTable extends Table
{
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
     * @param  int|null  $size
     * @return void
     */
    public function column($name, $type, $size = null)
    {
        $this->columns[$name] = [$type, $size];

        parent::column($name, $type, $size);
    }

    /**
     * Update a row of the table.
     *
     * @param  string  $key
     * @param  array  $values
     * @return void
     */
    public function set($key, array $values)
    {
        collect($values)
            ->each($this->ensureColumnsSize());

        parent::set($key, $values);
    }

    /**
     * Gets a closure that validates columns sizes.
     *
     * @return void
     */
    protected function ensureColumnsSize()
    {
        return function ($value, $column) {
            if (! Arr::has($this->columns, $column)) {
                return;
            }

            [$type, $size] = $this->columns[$column];

            if ($type == static::TYPE_STRING && strlen($value) > $size) {
                throw new ValueTooLargeForColumnException(sprintf(
                    'Value [%s...] is too large for [%s] column.',
                    substr($value, 0, 20),
                    $column,
                ));
            }
        };
    }
}

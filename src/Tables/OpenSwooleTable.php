<?php

namespace Laravel\Octane\Tables;

use Illuminate\Support\Arr;
use Laravel\Octane\Exceptions\ValueTooLargeForColumnException;
use Swoole\Table;

class OpenSwooleTable extends Table
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

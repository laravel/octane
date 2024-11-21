<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Exceptions\ValueTooLargeForColumnException;
use Laravel\Octane\Tables\OpenSwooleTable;
use Laravel\Octane\Tables\SwooleTable;
use Laravel\Octane\Tables\TableFactory;
use Swoole\Table;

class TableTest extends TestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('openswoole') && ! extension_loaded('swoole')) {
            $this->markTestSkipped('Require openswoole/swoole extension');
        }

        parent::setUp();
    }

    public function test_it_gets_used_while_creating_an_octane_store()
    {
        $serverState = ['octaneConfig' => ['cache' => [
            'rows' => 1000,
            'bytes' => 10000,
        ]]];

        $table = require __DIR__.'/../bin/createSwooleCacheTable.php';

        if (extension_loaded('openswoole')) {
            return $this->assertInstanceOf(OpenSwooleTable::class, $table);
        }

        return $this->assertInstanceOf(SwooleTable::class, $table);
    }

    public function test_it_gets_used_while_creating_an_table()
    {
        $serverState = ['octaneConfig' => ['tables' => [
            'example:1000' => [
                'name' => 'string:1000',
                'votes' => 'int',
            ],
        ]]];

        $tables = require __DIR__.'/../bin/createSwooleTables.php';

        $this->assertCount(1, $tables);

        if (extension_loaded('openswoole')) {
            return $this->assertInstanceOf(OpenSwooleTable::class, $tables['example']);
        }

        if (extension_loaded('openswoole')) {
            return $this->assertInstanceOf(OpenSwooleTable::class, $table);
        }

        return $this->assertInstanceOf(SwooleTable::class, $table);
    }

    public function test_it_gets_used_while_creating_an_timer_table()
    {
        $serverState = ['octaneConfig' => ['max_execution_time' => 30]];

        $table = require __DIR__.'/../bin/createSwooleTimerTable.php';

        if (extension_loaded('openswoole')) {
            return $this->assertInstanceOf(OpenSwooleTable::class, $table);
        }

        return $this->assertInstanceOf(SwooleTable::class, $table);
    }

    public function test_can_use_all_available_rows()
    {
        $table = $this->createSwooleTable();

        for ($i = 0; $i < 1000; $i++) {
            $table->set($i, ['string' => 'foo']);
        }

        $this->assertSame(1000, $table->count());
    }

    /**
     * @dataProvider validStringValues
     */
    public function test_set_of_string_columns($value)
    {
        $table = $this->createSwooleTable();

        $table->set('key', ['string' => $value]);

        $this->assertSame($value, $table->get('key')['string']);
    }

    /**
     * @dataProvider tooLargeStringValues
     */
    public function test_ensures_string_sizes($value)
    {
        $table = $this->createSwooleTable();

        $this->expectException(ValueTooLargeForColumnException::class);
        $this->expectExceptionMessage(sprintf(
            'Value [%s...] is too large for [string] column.',
            substr($value, 0, 20),
        ));

        $table->set('key', ['string' => $value]);
    }

    public function test_ensures_string_sizes_only_on_declared_columns()
    {
        $table = $this->createSwooleTable();

        $table->set('key', ['non-existing-column' => 'foo']);

        $this->assertArrayNotHasKey(
            'non-existing-column',
            $table->get('key'),
        );
    }

    public function test_ensures_string_sizes_only_on_string_types()
    {
        $table = $this->createSwooleTable();

        $value = 1000000000000000000;

        $table->set('key', ['int' => $value]);

        $this->assertSame($value, $table->get('key')['int']);
    }

    /**
     * @dataProvider
     */
    public static function validStringValues()
    {
        return [
            [str_repeat('a', 10)],
            ['taylortayl'],
            ['手 田日'],
            ['! p8VrB[]{'],
            ['N Rs*Gz2@h'],
            ['O:29:"Illu'],
            ['          '],
        ];
    }

    /**
     * @dataProvider
     */
    public static function tooLargeStringValues()
    {
        return [
            [str_repeat('a', 11)],
            ['taylortaylo'],
            ['手 田日尸'],
            ['! p8VrB[]{@'],
            ['N Rs*Gz2@hS'],
            ['O:29:"Illum'],
            ['           '],
        ];
    }

    protected function createSwooleTable()
    {
        return tap(TableFactory::make(1000), function ($table) {
            $table->column('string', Table::TYPE_STRING, 10);
            $table->column('int', Table::TYPE_INT);

            $table->create();
        });
    }
}

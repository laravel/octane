<?php

namespace Laravel\Octane\Tests;

use ArrayObject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Octane\Cache\OctaneStore;

class OctaneStoreTest extends TestCase
{
    /** @test */
    public function test_can_retrieve_items_from_store()
    {
        $table = new ArrayObject;

        $table['foo'] = ['value' => serialize('bar'), 'expiration' => time() + 100];

        $store = new OctaneStore($table);

        $this->assertEquals('bar', $store->get('foo'));
    }

    /** @test */
    public function test_missing_items_return_null()
    {
        $table = new ArrayObject;

        $store = new OctaneStore($table);

        $this->assertNull($store->get('foo'));
    }

    /** @test */
    public function test_expired_items_return_null()
    {
        $table = new ArrayObject;

        $store = new OctaneStore($table);

        $table['foo'] = ['value' => serialize('bar'), 'expiration' => time() - 100];

        $this->assertNull($store->get('foo'));
    }

    /** @test */
    public function test_get_method_can_resolve_pending_interval()
    {
        $table = new ArrayObject;

        $store = new OctaneStore($table);

        $store->interval('foo', fn () => 'bar', 1);

        $this->assertEquals('bar', $store->get('foo'));
    }

    /** @test */
    public function test_many_method_can_return_many_values()
    {
        $table = new ArrayObject;

        $table['foo'] = ['value' => serialize('bar'), 'expiration' => time() + 100];
        $table['bar'] = ['value' => serialize('baz'), 'expiration' => time() + 100];

        $store = new OctaneStore($table);

        $this->assertEquals(['foo' => 'bar', 'bar' => 'baz'], $store->many(['foo', 'bar']));
    }

    /** @test */
    public function test_put_stores_value_in_table()
    {
        $table = new ArrayObject;

        $store = new OctaneStore($table);

        $store->put('foo', 'bar', 5);

        $this->assertEquals('bar', $store->get('foo'));
    }

    /** @test */
    public function test_put_many_stores_value_in_table()
    {
        $table = new ArrayObject;

        $store = new OctaneStore($table);

        $store->putMany(['foo' => 'bar', 'bar' => 'baz'], 5);

        $this->assertEquals('bar', $store->get('foo'));
        $this->assertEquals('baz', $store->get('bar'));
    }

    /** @test */
    public function test_increment_and_decrement_operations()
    {
        $table = new ArrayObject;

        $store = new OctaneStore($table);

        $store->increment('counter');
        $this->assertEquals(1, $store->get('counter'));

        $store->increment('counter', 2);
        $this->assertEquals(3, $store->get('counter'));

        $store->decrement('counter', 2);
        $this->assertEquals(1, $store->get('counter'));
    }

    /** @test */
    public function test_forever_stores_value_in_table()
    {
        $table = new ArrayObject;

        $store = new OctaneStore($table);

        $store->forever('foo', 'bar');

        $this->assertEquals('bar', $store->get('foo'));
    }

    /** @test */
    public function test_intervals_can_be_refreshed()
    {
        $table = new ArrayObject;

        $store = new OctaneStore($table);

        $store->interval('foo', fn () => Str::random(10), 1);

        $this->assertTrue(is_string($first = $store->get('foo')));

        Carbon::setTestNow(now()->addMinutes(1));

        $store->refreshIntervalCaches();

        $this->assertTrue(is_string($second = $store->get('foo')));
        $this->assertNotEquals($first, $second);

        Carbon::setTestNow();
    }

    /** @test */
    public function test_can_forget_cache_items()
    {
        $table = new ArrayObject;

        $store = new OctaneStore($table);

        $store->put('foo', 'bar', 5);
        $this->assertTrue($store->forget('foo'));

        $this->assertNull($store->get('foo'));

        $store->put('foo', 'bar', 5);
        $this->assertTrue($store->flush());

        $this->assertNull($store->get('foo'));
    }

    /** @test */
    public function test_intervals_are_not_flushed()
    {
        $table = new ArrayObject;

        $store = new OctaneStore($table);

        $store->interval('foo', fn () => 'bar', 1);
        $this->assertTrue($store->flush());

        $this->assertEquals('bar', $store->get('foo'));
    }
}

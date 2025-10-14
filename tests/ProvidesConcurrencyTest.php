<?php

namespace Laravel\Octane\Tests;

use InvalidArgumentException;
use Laravel\Octane\Concerns\ProvidesConcurrencySupport;

class ProvidesConcurrencyTest extends TestCase
{
    private function fakeClass(): object
    {
        return new class
        {
            use ProvidesConcurrencySupport;
        };
    }

    public function test_concurrently_fails_with_empty_tasks()
    {
        $fakeClass = $this->fakeClass();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tasks cannot be an empty array');

        $fakeClass->concurrently([]);
    }

    public function test_concurrently_does_not_fail_with_not_empty_tasks()
    {
        $fakeClass = $this->fakeClass();


        $results = $fakeClass->concurrently([
            fn() => 1 + 1,
            fn() => 2 + 2,
        ]);

        $this->assertEquals([2, 4], $results);
    }
}

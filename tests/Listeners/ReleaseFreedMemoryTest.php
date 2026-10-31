<?php

namespace Laravel\Octane\Listeners;

use Laravel\Octane\Tests\TestCase;

class ReleaseFreedMemoryTest extends TestCase
{
    public function test_freed_memory_is_released_to_the_system()
    {
        $app = $this->createApplication();
        $app['config']->set('octane.garbage', 1);

        $strings = [];

        for ($i = 0; $i < 200000; $i++) {
            $strings[] = str_repeat('x', 100).$i;
        }

        unset($strings);

        $reserved = memory_get_usage(true);

        (new ReleaseFreedMemory)->handle((object) ['app' => $app]);

        $this->assertLessThan($reserved - 10 * 1024 * 1024, memory_get_usage(true));
    }
}

<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\Concerns\InteractsWithTerminal;

class TerminalTest extends TestCase
{
    use InteractsWithTerminal;

    public function test_width()
    {
        $this->assertGreaterThanOrEqual(30, $this->getTerminalWidth());
    }
}

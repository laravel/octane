<?php

namespace Laravel\Octane\Tests\Listeners;

class TestEvent implements TestEventInterface
{

    public function __construct(public $app)
    {
    }

}

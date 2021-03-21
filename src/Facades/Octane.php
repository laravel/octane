<?php

namespace Laravel\Octane\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Laravel\Octane\Octane
 */
class Octane extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'octane';
    }
}

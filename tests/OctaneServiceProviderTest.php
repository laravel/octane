<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Contracts\Octane as OctaneContract;
use Laravel\Octane\Octane;
use Laravel\Octane\OctaneServiceProvider;

class OctaneServiceProviderTest extends TestCase
{
    public function testOctaneContractIsBoundToOctaneImplementation(): void
    {
        $app = $this->createApplication();
        $app->register(new OctaneServiceProvider($app));

        $this->assertInstanceOf(Octane::class, $app->make(OctaneContract::class));
    }

    public function testOctaneIsBoundAsASingleton(): void
    {
        $app = $this->createApplication();
        $app->register(new OctaneServiceProvider($app));

        $this->assertSame(
            $app->make(OctaneContract::class),
            $app->make(OctaneContract::class)
        );
    }
}

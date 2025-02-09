<?php

namespace Laravel\Octane\Listeners;

use Inertia\ResponseFactory;

class PrepareInertiaForNextOperation
{

    /**
     * @var ?ResponseFactory
     */
    private $factory = null;

    public function __construct()
    {
        if (app()->resolved(ResponseFactory::class)) {
            $this->factory = app(ResponseFactory::class);
        }
    }

    public function handle($event): void
    {
        if ($this->factory === null) {
            return;
        }

        if (method_exists($this->factory, 'flushShared')) {
            $this->factory->flushShared();
        }
    }
}

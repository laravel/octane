<?php

namespace Laravel\Octane\Listeners;

use Laravel\Scout\EngineManager;

class PrepareScoutForNextOperation
{

    /**
     * @var ?EngineManager
     */
    private $engineManager = null;

    public function __construct()
    {
        if (app()->resolved(EngineManager::class)) {
            $this->engineManager = app(EngineManager::class);
        }
    }

    public function handle($event): void
    {
        if ($this->engineManager === null) {
            return;
        }

        if (method_exists($this->engineManager, 'forgetEngines')) {
            $this->engineManager->forgetEngines();
        }
    }
}

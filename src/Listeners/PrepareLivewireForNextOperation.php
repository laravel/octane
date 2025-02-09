<?php

namespace Laravel\Octane\Listeners;

use Livewire\LivewireManager;

class PrepareLivewireForNextOperation
{

    /**
     * @var ?LivewireManager
     */
    private $manager = null;

    public function __construct()
    {
        if (app()->resolved(LivewireManager::class)) {
            $this->manager = app(LivewireManager::class);
        }
    }

    public function handle($event): void
    {
        if ($this->manager === null) {
            return;
        }

        if (method_exists($this->manager, 'flushState')) {
            $this->manager->flushState();
        }
    }
}

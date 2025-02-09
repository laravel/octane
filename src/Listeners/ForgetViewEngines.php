<?php

namespace Laravel\Octane\Listeners;

use Illuminate\View\Engines\EngineResolver;

class ForgetViewEngines
{

    /**
     * @var ?EngineResolver
     */
    private $view = null;

    public function __construct()
    {
        if (app()->resolved('view.engine.resolver')) {
            $this->view = app('view.engine.resolver');
        }
    }

    public function handle($event)
    {
        if ($this->view === null) {
            return;
        }

        $this->view->forget('blade');
        $this->view->forget('php');
    }

}

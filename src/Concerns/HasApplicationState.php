<?php

namespace Laravel\Octane\Concerns;

use Laravel\Octane\ApplicationState;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

trait HasApplicationState
{
    protected ApplicationState $appState;

    protected function captureApplicationState(Application $app)
    {
        if (!isset($this->appState)) {
            $this->appState = new ApplicationState($app);
        }

        return $this->appState->appState;
    }

    protected function restoreApplicationState(Application $app)
    {
        $this->appState->loadInto($app);

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        return $app;
    }
}
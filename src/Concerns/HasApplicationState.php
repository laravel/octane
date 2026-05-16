<?php

namespace Laravel\Octane\Concerns;

use Laravel\Octane\ApplicationState;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

trait HasApplicationState
{
    protected ApplicationState $appState;

    /**
     * Capture the application state.
     * Reset the original application passed to the captured state.
     *
     * @return array{0: Application, 1: ApplicationState}
     */
    protected function captureApplicationState(Application $app)
    {
        if (! isset($this->appState)) {
            $this->appState = new ApplicationState($app);
        } else {
            $this->appState->loadInto($app);
        }

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        return [$this->appState, $app];
    }
}
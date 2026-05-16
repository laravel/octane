<?php

namespace Laravel\Octane\Concerns;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Laravel\Octane\ApplicationState;

trait HasApplicationState
{
    protected ApplicationState $appState;

    /**
     * Capture the application state.
     * Reset the original application passed to the captured state.
     *
     * @return array{0: ApplicationState, 1: Application}
     */
    protected function captureApplicationState(Application $app)
    {
        if (! isset($this->appState)) {
            $this->appState = new ApplicationState($app);
        } else {
            $this->appState->loadInto($app);
        }

        Facade::clearResolvedInstances();

        return [$this->appState, $app];
    }
}

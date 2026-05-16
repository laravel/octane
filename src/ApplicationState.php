<?php

namespace Laravel\Octane;
use Illuminate\Foundation\Application;

class ApplicationState extends Application
{

    public Application $appState;

    public function __construct(Application $app)
    {
        $this->appState = clone $app;

        // skip parent constructor
    }

    /**
     * Load the state into the application.
     */
    public function loadInto(Application $app)
    {
        foreach (get_object_vars($this->appState) as $key => $value) {
            $app->$key = $value;
        }
    }
}
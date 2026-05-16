<?php

namespace Laravel\Octane;

use Illuminate\Foundation\Application;

class ApplicationState extends Application
{
    public function __construct(Application $app)
    {
        foreach (get_object_vars($app) as $key => $value) {
            $this->$key = $value;
        }
        // skip parent constructor
    }

    /**
     * Load the state into the application.
     */
    public function loadInto(Application $app)
    {
        foreach (get_object_vars($this) as $key => $value) {
            $app->$key = $value;
        }
    }
}

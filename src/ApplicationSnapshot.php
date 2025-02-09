<?php

namespace Laravel\Octane;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

class ApplicationSnapshot extends Application
{
    public static function createSnapshotFrom(Application $app): ApplicationSnapshot
    {
        $previousInstance = Container::getInstance();
        $snapshot = new ApplicationSnapshot;
        foreach (get_object_vars($app) as $key => $value) {
            $snapshot->$key = $value;
        }
        Container::setInstance($previousInstance);

        return $snapshot;
    }

    public function loadSnapshotInto(Application $app): void
    {
        foreach (get_object_vars($this) as $key => $value) {
            $app->$key = $value;
        }
        Facade::clearResolvedInstances();
    }

    public function initialInstance(string $abstract)
    {
        if (! array_key_exists($abstract, $this->resolved)) {
            return null;
        }

        return $this->instances[$abstract] ?? $this->bindings[$abstract]['concrete']();
    }
}

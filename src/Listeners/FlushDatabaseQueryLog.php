<?php

namespace Laravel\Octane\Listeners;

class FlushDatabaseQueryLog
{

    /**
     * @var ?\Illuminate\Database\DatabaseManager
     */
    private $db = null;

    public function __construct()
    {
        if (app()->resolved('db')) {
            $this->db = app()->make('db');
        }
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $this->db) {
            return;
        }

        foreach ($this->db->getConnections() as $connection) {
            $connection->flushQueryLog();
        }
    }
}

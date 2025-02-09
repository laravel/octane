<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Database\DatabaseManager;

class RefreshQueryDurationHandling
{
    /**
     * @var ?DatabaseManager
     */
    private $db = null;

    public function __construct()
    {
        if (app()->resolved('db')) {
            $this->db = app('db');
        }
    }

    public function handle($event): void
    {
        if ($this->db === null) {
            return;
        }

        foreach ($this->db->getConnections() as $connection) {
            if (
                method_exists($connection, 'resetTotalQueryDuration')
                && method_exists($connection, 'allowQueryDurationHandlersToRunAgain')
            ) {
                $connection->resetTotalQueryDuration();
                $connection->allowQueryDurationHandlersToRunAgain();
            }
        }
    }
}

<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Log\LogManager;
use Monolog\ResettableInterface;

class FlushMonologState
{

    /**
     * @var ?LogManager
     */
    private $log = null;

    public function __construct()
    {
        if (app()->resolved('log')) {
            $this->log = app('log');
        }
    }

    public function handle($event): void
    {
        if (!$this->log) {
            return;
        }

        foreach ($this->log->getChannels() as $channel) {
            $logger = $channel->getLogger();
            if ($logger instanceof ResettableInterface) {
                $logger->reset();
            }
        }
    }
}

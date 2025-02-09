<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Notifications\ChannelManager;

class GiveNewApplicationInstanceToNotificationChannelManager
{

    /**
     * @var ?ChannelManager
     */
    private $channelManager = null;

    public function __construct()
    {
        if (app()->resolved(ChannelManager::class)) {
            $this->channelManager = app(ChannelManager::class);
        }
    }

    public function handle($event): void
    {
        if ($this->channelManager === null) {
            return;
        }

        $this->channelManager->forgetDrivers();
    }
}

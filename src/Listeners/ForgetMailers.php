<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Mail\MailManager;

class ForgetMailers
{

    /**
     * @var ?MailManager
     */
    private $mailManager = null;

    public function __construct()
    {
        if (app()->resolved('mail.manager')) {
            $this->mailManager = app('mail.manager');
        }
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if ($this->mailManager === null) {
            return;
        }

        $this->mailManager->forgetMailers();
    }
}

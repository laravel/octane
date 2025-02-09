<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Support\NamespacedItemResolver;
use Illuminate\Translation\Translator;

class FlushTranslatorCache
{

    /**
     * @var ?Translator
     */
    private $translator = null;

    public function __construct()
    {
        if (app()->resolved('translator')) {
            $this->translator = app('translator');
        }
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        if ($this->translator === null) {
            return;
        }

        if ($this->translator instanceof NamespacedItemResolver) {
            $this->translator->flushParsedKeys();
        }
    }
}

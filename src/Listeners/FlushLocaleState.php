<?php

namespace Laravel\Octane\Listeners;

use Carbon\Carbon;
use Carbon\Laravel\ServiceProvider as CarbonServiceProvider;

class FlushLocaleState
{

    /**
     * @var ?\Illuminate\Translation\Translator
     */
    private $translator = null;
    private $initialAppLocale;
    private $initialAppFallbackLocale;

    public function __construct()
    {
        if (app()->resolved('translator')) {
            $this->translator = app()->make('translator');
        }
        $this->initialAppLocale = config('app.locale');
        $this->initialAppFallbackLocale = config('fallback_locale');
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if ($this->translator) {
            $this->translator->setLocale($this->initialAppLocale);
            $this->translator->setFallback($this->initialAppFallbackLocale);
        }

        if (Carbon::getLocale() !== $this->initialAppLocale) {
            (new CarbonServiceProvider($event->sandbox))->updateLocale();
        }
    }
}

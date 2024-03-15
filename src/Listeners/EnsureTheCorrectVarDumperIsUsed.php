<?php

namespace Laravel\Octane\Listeners;

use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

class EnsureTheCorrectVarDumperIsUsed
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        VarDumper::setHandler(function ($var, ?string $label = null) {
            $cloner = new VarCloner();
            $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);
            $var = $cloner->cloneVar($var);
            if (null !== $label) {
                $var = $var->withContext(['label' => $label]);
            }
            $dumper = app()->runningInConsole() ? new CliDumper() : new HtmlDumper();
            $dumper->dump($var);
        });
    }
}

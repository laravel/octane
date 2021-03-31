<?php

namespace Laravel\Octane\Commands;

use Illuminate\Console\Command as BaseCommand;
use Laravel\Octane\Commands\Concerns\InteractsWithIO;
use Laravel\Octane\Commands\Concerns\InteractsWithServers;

class Command extends BaseCommand
{
    use InteractsWithIO, InteractsWithServers;
}

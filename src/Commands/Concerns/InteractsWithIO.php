<?php

namespace Laravel\Octane\Commands\Concerns;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use Laravel\Octane\Exceptions\DdException;
use Laravel\Octane\Exceptions\ServerShutdownException;
use Laravel\Octane\Exceptions\WorkerException;
use Laravel\Octane\WorkerExceptionInspector;
use NunoMaduro\Collision\Writer;
use Symfony\Component\VarDumper\VarDumper;

trait InteractsWithIO
{
    use InteractsWithTerminal;

    /**
     * A list of error messages that should be ignored.
     *
     * @var array
     */
    protected $ignoreMessages = [
        'scan command',
        'stop signal received, grace timeout is: ',
        'exit forced',
        'worker allocated',
        'worker is allocated',
        'worker constructed',
        'worker destructed',
        'worker destroyed',
        '[INFO] RoadRunner server started; version:',
    ];

    /**
     * Write a string as raw output.
     *
     * @param  string  $string
     * @return void
     */
    public function raw($string)
    {
        if (! Str::startsWith($string, $this->ignoreMessages)) {
            $this->output instanceof OutputStyle
                ? fwrite(STDERR, $string."\n")
                : $this->output->writeln($string);
        }
    }

    /**
     * Write a string as information output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function info($string, $verbosity = null)
    {
        $this->label($string, $verbosity, 'INFO', 'cyan', 'black');
    }

    /**
     * Write a string as error output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function error($string, $verbosity = null)
    {
        $this->label($string, $verbosity, 'ERROR', 'red', 'white');
    }

    /**
     * Write a string as warning output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function warn($string, $verbosity = null)
    {
        $this->label($string, $verbosity, 'WARN', 'yellow', 'black');
    }

    /**
     * Write a string as label output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @param  string  $level
     * @param  string  $background
     * @param  string  $foreground
     * @return void
     */
    public function label($string, $verbosity, $level, $background, $foreground)
    {
        if (! empty($string) && ! Str::startsWith($string, $this->ignoreMessages)) {
            $this->output->writeln([
                '',
                "  <bg=$background;fg=$foreground;options=bold> $level </> $string",
            ], $this->parseVerbosity($verbosity));
        }
    }

    /**
     * Write information about a request to the console.
     *
     * @param  array  $request
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function requestInfo($request, $verbosity = null)
    {
        $terminalWidth = $this->getTerminalWidth();

        $url = parse_url($request['url'], PHP_URL_PATH) ?: '/';
        $duration = number_format(round($request['duration'], 2), 2, '.', '');

        $memory = isset($request['memory'])
            ? (number_format($request['memory'] / 1024 / 1024, 2, '.', '').' mb ')
            : '';

        ['method' => $method, 'statusCode' => $statusCode] = $request;

        $dots = str_repeat('.', max($terminalWidth - strlen($method.$url.$duration.$memory) - 16, 0));

        if (empty($dots) && ! $this->output->isVerbose()) {
            $url = substr($url, 0, $terminalWidth - strlen($method.$duration.$memory) - 15 - 3).'...';
        } else {
            $dots .= ' ';
        }

        $this->output->writeln(sprintf(
           '  <fg=%s;options=bold>%s </>   <fg=cyan;options=bold>%s</> <options=bold>%s</><fg=#6C7280> %s%s%s ms</>',
            match (true) {
                $statusCode >= 500 => 'red',
                $statusCode >= 400 => 'yellow',
                $statusCode >= 300 => 'cyan',
                $statusCode >= 100 => 'green',
                default => 'white',
            },
           $statusCode,
           $method,
           $url,
           $dots,
           $memory,
           $duration,
        ), $this->parseVerbosity($verbosity));
    }

    /**
     * Write information about a dd to the console.
     *
     * @param  array  $throwable
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function ddInfo($throwable, $verbosity = null)
    {
        collect(json_decode($throwable['message'], true))
            ->each(fn ($var) => VarDumper::dump($var));
    }

    /**
     * Write information about a throwable to the console.
     *
     * @param  array  $throwable
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function throwableInfo($throwable, $verbosity = null)
    {
        if ($throwable['class'] == DdException::class) {
            return $this->ddInfo($throwable, $verbosity);
        }

        if (! class_exists('NunoMaduro\Collision\Writer')) {
            $this->label($throwable['message'], $verbosity, $throwable['class'], 'red', 'white');

            $this->newLine();

            $outputTrace = function ($trace, $number) {
                $number++;

                ['line' => $line, 'file' => $file] = $trace;

                $this->line("  <fg=yellow>$number</>   $file:$line");
            };

            $outputTrace($throwable, -1);

            return collect($throwable['trace'])->each($outputTrace);
        }

        (new Writer(null, $this->output))->write(
            new WorkerExceptionInspector(
                new WorkerException(
                    $throwable['message'],
                    (int) $throwable['code'],
                    $throwable['file'],
                    (int) $throwable['line'],
                ),
                $throwable['class'],
                $throwable['trace'],
            ),
        );
    }

    /**
     * Write information about a "shutdown" throwable to the console.
     *
     * @param  array  $throwable
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function shutdownInfo($throwable, $verbosity = null)
    {
        $this->throwableInfo($throwable, $verbosity);

        throw new ServerShutdownException;
    }

    /**
     * Handle stream information from the worker.
     *
     * @param  array  $stream
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function handleStream($stream, $verbosity = null)
    {
        match ($stream['type'] ?? null) {
            'request' => $this->requestInfo($stream, $verbosity),
            'throwable' => $this->throwableInfo($stream, $verbosity),
            'shutdown' => $this->shutdownInfo($stream, $verbosity),
            default => $this->info(json_encode($stream), $verbosity)
        };
    }
}

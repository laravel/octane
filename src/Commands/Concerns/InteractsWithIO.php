<?php

namespace Laravel\Octane\Commands\Concerns;

use Laravel\Octane\Exceptions\WorkerException;
use Laravel\Octane\WorkerExceptionInspector;
use NunoMaduro\Collision\Writer;

trait InteractsWithIO
{
    use InteractsWithTerminal;

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
     * Write a string as label output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @param  string  $level
     * @param  string  $background
     * @param  string  $foreground
     * @return void
     */
    protected function label($string, $verbosity, $level, $background, $foreground)
    {
        if (! empty($string)) {
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

        ['method' => $method, 'statusCode' => $statusCode] = $request;

        $dots = str_repeat('.', max($terminalWidth - strlen($method.$url.$duration) - 16, 0));

        if (empty($dots) && ! $this->output->isVerbose()) {
            $url = substr($url, 0, $terminalWidth - strlen($method.$duration) - 15 - 3).'...';
        } else {
            $dots .= ' ';
        }

        $this->output->writeln(sprintf(
           '  <fg=%s;options=bold>%s </>   <fg=cyan;options=bold>%s</> <options=bold>%s</><fg=#6C7280> %s%s ms</>',
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
           $duration,
        ), $this->parseVerbosity($verbosity));
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
                    $throwable['code'],
                    $throwable['file'],
                    $throwable['line'],
                ),
                $throwable['class'],
                $throwable['trace'],
            ),
        );
    }
}

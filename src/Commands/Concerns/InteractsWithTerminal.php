<?php

namespace Laravel\Octane\Commands\Concerns;

use Symfony\Component\Console\Terminal;

trait InteractsWithTerminal
{
    /**
     * The current terminal width.
     *
     * @var int|null
     */
    protected $terminalWidth;

    /**
     * Computes the terminal width.
     *
     * @return int
     */
    protected function getTerminalWidth()
    {
        if ($this->terminalWidth == null) {
            $this->terminalWidth = (new Terminal)->getWidth();

            $this->terminalWidth = $this->terminalWidth >= 30
                ? $this->terminalWidth
                : 30;
        }

        return $this->terminalWidth;
    }
}

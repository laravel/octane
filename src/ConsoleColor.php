<?php

namespace Laravel\Octane;

/**
 * Source: https://gist.github.com/superbrothers/3431198
 */
class ConsoleColor
{
    protected static $ANSI_CODES = [
        "off" => 0,
        "bold" => 1,
        "italic" => 3,
        "underline" => 4,
        "blink" => 5,
        "inverse" => 7,
        "hidden" => 8,
        "black" => 30,
        "red" => 31,
        "green" => 32,
        "yellow" => 33,
        "blue" => 34,
        "magenta" => 35,
        "cyan" => 36,
        "white" => 37,
        "black_bg" => 40,
        "red_bg" => 41,
        "green_bg" => 42,
        "yellow_bg" => 43,
        "blue_bg" => 44,
        "magenta_bg" => 45,
        "cyan_bg" => 46,
        "white_bg" => 47,
    ];

    /**
     * Set the ANSI color codes for the given string.
     *
     * @param  string  $string
     * @param  string  $color
     * @return string
     */
    public static function set(string $string, string $color): string
    {
        $result = '';

        foreach (explode("+", $color) as $attribute) {
            $result .= "\033[".static::$ANSI_CODES[$attribute]."m";
        }

        return $result.$string."\033[".static::$ANSI_CODES["off"]."m";
    }
}

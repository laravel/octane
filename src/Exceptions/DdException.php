<?php

namespace Laravel\Octane\Exceptions;

use Exception;
use Illuminate\Contracts\Support\Renderable;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class DdException extends Exception implements Renderable
{
    public function __construct(public array $vars)
    {
        $this->message = json_encode($vars);
    }

    /**
     * Get the evaluated contents of the object.
     *
     * @return string
     */
    public function render()
    {
        $dump = function ($var) {
            $data = (new VarCloner())->cloneVar($var)->withMaxDepth(3);

            return (string) (new HtmlDumper(false))->dump($data, true, [
                'maxDepth' => 3,
                'maxStringLength' => 160,
            ]);
        };

        return collect($this->vars)->map($dump)->implode('');
    }
}

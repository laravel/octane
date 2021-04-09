<?php

namespace Laravel\Octane;

use Symfony\Component\HttpFoundation\Response;

class OctaneResponse
{
    public function __construct(public Response $response, public ?string $outputBuffer = null)
    {
    }
}

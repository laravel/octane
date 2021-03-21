<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\Contracts\OperationTerminated;
use Symfony\Component\HttpFoundation\Response;

class RequestTerminated implements OperationTerminated
{
    use HasApplicationAndSandbox;

    public function __construct(
        public Application $app,
        public Application $sandbox,
        public Request $request,
        public Response $response
    ) {
    }
}

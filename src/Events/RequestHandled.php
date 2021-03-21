<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestHandled
{
    public function __construct(
        public Application $sandbox,
        public Request $request,
        public Response $response
    ) {
    }
}

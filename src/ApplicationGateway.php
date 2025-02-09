<?php

namespace Laravel\Octane;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Facades\Octane;
use Symfony\Component\HttpFoundation\Response;

class ApplicationGateway
{

    public function __construct(protected OctaneEventListener $listener, protected Application $snapshot, protected Application $sandbox)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request): Response
    {
        $request->enableHttpMethodParameterOverride();

        $this->listener->dispatchEvent(new RequestReceived($this->snapshot, $this->sandbox, $request));

        if (Octane::hasRouteFor($request->getMethod(), '/'.$request->path())) {
            return Octane::invokeRoute($request, $request->getMethod(), '/'.$request->path());
        }

        // TODO: no tap
        return tap($this->snapshot->initialInstance(Kernel::class)->handle($request), function ($response) use ($request) {
            $this->listener->dispatchEvent(new RequestHandled($this->sandbox, $request, $response));
        });
    }

    /**
     * "Shut down" the application after a request.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->snapshot->initialInstance(Kernel::class)->terminate($request, $response);

        $this->listener->dispatchEvent(new RequestTerminated($this->snapshot, $this->sandbox, $request, $response));

        $route = $request->route();

        if ($route instanceof Route && method_exists($route, 'flushController')) {
            $route->flushController();
        }
    }
}

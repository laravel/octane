<?php

namespace Laravel\Octane\Contracts;

use Illuminate\Http\Request;
use Laravel\Octane\RequestContext;

interface Worker
{
    /**
     * Boot / initialize the Octane worker.
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Handle an incoming request and send the response to the client.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Laravel\Octane\RequestContext  $context
     * @return void
     */
    public function handle(Request $request, RequestContext $context): void;

    /**
     * Handle an incoming task.
     *
     * @param  mixed  $data
     * @return mixed
     */
    public function handleTask($data);

    /**
     * Terminate the worker.
     *
     * @return void
     */
    public function terminate(): void;
}

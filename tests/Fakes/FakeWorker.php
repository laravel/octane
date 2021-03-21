<?php

namespace Laravel\Octane\Tests\Fakes;

use Laravel\Octane\RequestContext;
use Laravel\Octane\Worker;

class FakeWorker extends Worker
{
    public function run()
    {
        foreach ($this->client->requests as $request) {
            [$request, $context] = $this->client->marshalRequest(
                new RequestContext(['request' => $request])
            );

            $this->handle($request, $context);
        }
    }
}

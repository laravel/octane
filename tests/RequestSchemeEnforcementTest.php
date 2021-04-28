<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class RequestSchemeEnforcementTest extends TestCase
{
    public function test_request_scheme_is_enforced()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app['config']['octane.https'] = true;

        $app['router']->get('/first', function (Application $app, Request $request) {
            return [
                'url' => $app['url']->to('/foo'),
                'secure' => $request->isSecure(),
            ];
        });

        $worker->run();

        $this->assertEquals('https://localhost/foo', $client->responses[0]->original['url']);
        $this->assertEquals(true, $client->responses[0]->original['secure']);
    }
}

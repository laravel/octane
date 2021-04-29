<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;

class RequestServerPortTest extends TestCase
{
    public function test_request_server_port_is_correct(): void
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('http://localhost/server-port', 'GET'),
            Request::create('https://localhost/server-port', 'GET'),
        ]);

        $app['router']->get('/server-port', function (Request $request) {
            return $request->server->get('SERVER_PORT');
        });

        $worker->run();

        $this->assertEquals(80, $client->responses[0]->original);
        $this->assertEquals(443, $client->responses[1]->original);
    }
}

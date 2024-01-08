<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class UrlTest extends TestCase
{
    public function test_url_defaults_are_flushed()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/b', 'GET'),
            Request::create('/a', 'GET'),
            Request::create('/b', 'GET'),
        ]);

        $app['router']->get('a', function () {
            URL::defaults(['locale' => 'default']);

            return ['default-parameters' => URL::getDefaultParameters()];
        });

        $app['router']->get('b/{locale?}', function (?string $locale = null) {
            return [
                'locale' => $locale ?: 'none',
                'default-parameters' => URL::getDefaultParameters(),
            ];
        });

        $worker->run();

        $this->assertEquals([
            'locale' => 'none',
            'default-parameters' => [],
        ], json_decode($client->responses[0]->getContent(), true));

        $this->assertEquals([
            'default-parameters' => [
                'locale' => 'default',
            ],
        ], json_decode($client->responses[1]->getContent(), true));

        $this->assertEquals([
            'locale' => 'none',
            'default-parameters' => [],
        ], json_decode($client->responses[0]->getContent(), true));
    }
}

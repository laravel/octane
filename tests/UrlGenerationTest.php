<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class UrlGenerationTest extends TestCase
{
    public function test_url_generator_creates_correct_urls_across_subsequent_requests(): void
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->get('/first', function (Application $app) {
            return $app['url']->current();
        });

        $app['router']->get('/second', function (Application $app) {
            return $app['url']->current();
        });

        $worker->run();

        $this->assertEquals('http://localhost/first', $client->responses[0]->getContent());
        $this->assertEquals('http://localhost/second', $client->responses[1]->getContent());
    }
}

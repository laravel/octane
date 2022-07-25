<?php

namespace Laravel\Octane\Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class LocaleStateTest extends TestCase
{
    public function test_translator_state_is_reset_across_subsequent_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/test-locale?locale=nl', 'GET'),
            Request::create('/test-locale', 'GET'),
            Request::create('/test-locale?locale=ms', 'GET'),
        ]);

        $app['router']->get('/test-locale', function (Application $app, Request $request) {
            if ($request->has('locale')) {
                $app->setLocale($request->query('locale'));
            }

            return $app->make('translator')->getLocale();
        });

        $worker->run();

        $this->assertEquals('nl', $client->responses[0]->getContent());
        $this->assertEquals('en', $client->responses[1]->getContent());
        $this->assertEquals('ms', $client->responses[2]->getContent());
    }

    public function test_carbon_state_is_reset_across_subsequent_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/test-locale', 'GET'), // should be "en"
            Request::create('/test-locale?locale=nl', 'GET'),
            Request::create('/test-locale', 'GET'), // should be "en", and not "nl"...
        ]);

        $app['router']->get('/test-locale', function (Application $app, Request $request) {
            if ($request->has('locale')) {
                Carbon::setLocale($request->query('locale'));
            }

            return now()->getLocale();
        });

        $worker->run();

        $this->assertEquals('en', $client->responses[0]->getContent());
        $this->assertEquals('nl', $client->responses[1]->getContent());
        $this->assertEquals('en', $client->responses[2]->getContent());
    }

    public function test_carbon_and_app_locale_gets_updated_when_changing_app_locale()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/test-locale', 'GET'), // should be "en"
            Request::create('/test-locale?locale=nl', 'GET'), // should be "nl"
            Request::create('/test-locale', 'GET'), // should be "en", and not "nl"
            Request::create('/test-locale?locale=pt', 'GET'), // should be "pt"
        ]);

        $app['router']->get('/test-locale', function (Application $app, Request $request) {
            if ($request->has('locale')) {
                app()->setLocale($request->query('locale'));
            }

            return [app()->getLocale(), now()->getLocale()];
        });

        $worker->run();

        $this->assertEquals(['en', 'en'], json_decode($client->responses[0]->getContent(), true));
        $this->assertEquals(['nl', 'nl'], json_decode($client->responses[1]->getContent(), true));
        $this->assertEquals(['en', 'en'], json_decode($client->responses[2]->getContent(), true));
        $this->assertEquals(['pt', 'pt'], json_decode($client->responses[3]->getContent(), true));
    }
}

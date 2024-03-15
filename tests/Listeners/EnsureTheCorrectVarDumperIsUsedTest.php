<?php

namespace Laravel\Octane\Tests\Listeners;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Laravel\Octane\Tests\TestCase;

class EnsureTheCorrectVarDumperIsUsedTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_RUNNING_IN_CONSOLE'] = false;
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_RUNNING_IN_CONSOLE']);
        parent::tearDown();
    }

    public function test_a_variable_is_contained_within_the_html_if_dumped_in_a_blade_view()
    {
        $variableToDump = 'someString';
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/view', 'GET'),
        ]);
        $app['router']->get('/view', function (Application $app) use ($variableToDump) {
            return Blade::render('@dump($variableToDump)', [
                'variableToDump' => $variableToDump,
            ]);
        });

        $worker->run();

        $htmlFromResponse = $client->responses[0]->getContent();
        $this->assertStringContainsString($variableToDump, $htmlFromResponse);
    }

}

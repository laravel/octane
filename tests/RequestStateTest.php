<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class RequestStateTest extends TestCase
{
    public function test_request_is_rebound_on_sandbox(): void
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first?name=Taylor', 'GET'),
            Request::create('/first?name=Abigail', 'GET'),
        ]);

        $app->bind('test-binding', function ($app) {
            return $app['request'];
        });

        $app['router']->get('/first', function (Application $app) {
            return $app['test-binding']->query('name');
        });

        $worker->run();

        $this->assertEquals('Taylor', $client->responses[0]->getContent());
        $this->assertEquals('Abigail', $client->responses[1]->getContent());
    }

    public function test_form_requests_contain_the_correct_state_across_subsequent_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first?name=Taylor', 'GET'),
            Request::create('/first?name=Abigail', 'GET'),
        ]);

        $app['router']->get('/first', function (RequestStateTestFormRequest $request) {
            return [
                'name' => $request->query('name'),
                'container' => spl_object_hash($request->getContainer()),
            ];
        });

        $worker->run();

        $this->assertEquals('Taylor', $client->responses[0]->original['name']);
        $this->assertEquals('Abigail', $client->responses[1]->original['name']);
        $this->assertNotEquals($client->responses[0]->original['container'], $client->responses[1]->original['container']);
    }
}

class RequestStateTestFormRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            //
        ];
    }

    public function getContainer()
    {
        return $this->container;
    }
}

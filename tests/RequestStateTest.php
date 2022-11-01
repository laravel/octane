<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RequestStateTest extends TestCase
{
    public function test_request_is_rebound_on_sandbox()
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

    public function test_request_routes_flush_controller_state()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/users', 'GET'),
            Request::create('/users', 'GET'),
        ]);

        $app['router']->get('/users', UserControllerStub::class);

        $worker->run();

        $this->assertEquals(1, $client->responses[0]->original['invokedCount']);
        $this->assertEquals(1, $client->responses[0]->original['middlewareInvokedCount']);
        $this->assertEquals(1, $client->responses[1]->original['invokedCount']);
        $this->assertEquals(1, $client->responses[1]->original['middlewareInvokedCount']);

        $worker->run();

        $this->assertEquals(1, $client->responses[0]->original['invokedCount']);
        $this->assertEquals(1, $client->responses[0]->original['middlewareInvokedCount']);
        $this->assertEquals(1, $client->responses[1]->original['invokedCount']);
        $this->assertEquals(1, $client->responses[1]->original['middlewareInvokedCount']);
    }

    public function test_request_routes_controller_does_not_leak()
    {
        gc_collect_cycles();
        UserControllerStub::$destroyedCount = 0;

        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/users', 'GET'),
            Request::create('/users', 'GET'),
        ]);

        $app['router']->get('/users', UserControllerStub::class);

        $worker->run();

        gc_collect_cycles();
        $this->assertEquals(2, UserControllerStub::$destroyedCount);

        $worker->run();

        gc_collect_cycles();
        $this->assertEquals(4, UserControllerStub::$destroyedCount);
    }
}

class UserControllerStub extends Controller
{
    protected $middlewareInvokedCount = 0;

    protected $invokedCount = 0;

    public static $destroyedCount = 0;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->middlewareInvokedCount++;

            return $next($request);
        });
    }

    public function __invoke()
    {
        $this->invokedCount++;

        return [
            'middlewareInvokedCount' => $this->middlewareInvokedCount,
            'invokedCount' => $this->invokedCount,
        ];
    }

    public function __destruct()
    {
        static::$destroyedCount++;
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

<?php

namespace Laravel\Octane\Tests;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Exceptions\DdException;
use Laravel\Octane\Exceptions\TaskException;
use Laravel\Octane\Exceptions\TaskTimeoutException;
use Laravel\Octane\Facades\Octane;
use Laravel\Octane\SequentialTaskDispatcher;
use Laravel\Octane\Swoole\SwooleHttpTaskDispatcher;
use Laravel\Octane\Swoole\TaskPayload;
use Laravel\Octane\Swoole\TaskResult;
use Laravel\SerializableClosure\SerializableClosure;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\TestCase;

class SwooleHttpTaskDispatcherTest extends TestCase
{
    protected string $secret = 'Wl8zUj6XxpNlRkkNWqCEJQa3XzD4vyEmgo5hUmWy5lI=';

    public function test_tasks_can_be_resolved_via_http()
    {
        Http::fake([
            '127.0.0.1:8000/octane/resolve-tasks' => Http::response(
                TaskPayload::seal($this->secret, 'resolve-tasks-result', ['first' => 1, 'second' => 2, 'third' => null])
            ),
        ]);

        $this->assertEquals([
            'first' => 1,
            'second' => 2,
            'third' => null,
        ], $this->dispatcher()->resolve([
            'first' => fn () => 1,
            'second' => fn () => 2,
            'third' => function () {
            },
        ]));
    }

    public function test_tasks_can_be_dispatched_via_http()
    {
        Http::fake([
            '127.0.0.1:8000/octane/dispatch-tasks' => Http::response(),
        ]);

        $this->dispatcher()->dispatch([
            'first' => fn () => 1,
            'second' => fn () => 2,
        ]);

        Http::assertSentCount(1);
    }

    public function test_tasks_can_be_resolved_via_fallback_dispatcher()
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused.'));

        $this->assertEquals([
            'first' => 1,
            'second' => 2,
        ], $this->dispatcher()->resolve([
            'first' => fn () => 1,
            'second' => fn () => 2,
        ]));
    }

    public function test_tasks_can_be_dispatched_via_fallback_dispatcher()
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused.'));

        $ran = [];

        $this->dispatcher()->dispatch([
            'first' => function () use (&$ran) {
                $ran[] = 'first';
            },
            'second' => function () use (&$ran) {
                $ran[] = 'second';
            },
        ]);

        $this->assertSame(['first', 'second'], $ran);
    }

    public function test_rejected_dispatches_run_locally()
    {
        Http::fake([
            '127.0.0.1:8000/octane/dispatch-tasks' => Http::sequence()->push(null, 403)->push(null, 500),
        ]);

        foreach ([403 => 1, 500 => 0] as $status => $expected) {
            $ran = 0;

            $this->dispatcher()->dispatch([function () use (&$ran) {
                $ran++;
            }]);

            $this->assertSame($expected, $ran, "Status {$status}");
        }
    }

    public function test_tasks_run_locally_without_a_task_secret()
    {
        Http::fake();

        $dispatcher = new SwooleHttpTaskDispatcher('127.0.0.1', '8000', new SequentialTaskDispatcher);

        $this->assertSame(['first' => 1], $dispatcher->resolve(['first' => fn () => 1]));

        $dispatcher->dispatch(['first' => fn () => 1]);

        Http::assertNothingSent();
    }

    public function test_resolving_tasks_propagate_exceptions()
    {
        Http::fake([
            '127.0.0.1:8000/octane/resolve-tasks' => Http::response(null, 500),
        ]);

        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Invalid response from task server.');

        $this->dispatcher()->resolve(['first' => fn () => throw new Exception('Something went wrong.')]);
    }

    public function test_resolving_tasks_propagate_dd_calls()
    {
        Http::fake([
            '127.0.0.1:8000/octane/resolve-tasks' => Http::response(null, 500),
        ]);

        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Invalid response from task server.');

        $this->dispatcher()->resolve(['first' => fn () => throw new DdException(['foo' => 'bar'])]);
    }

    public function test_dispatching_tasks_do_not_propagate_exceptions()
    {
        Http::fake([
            '127.0.0.1:8000/octane/dispatch-tasks' => Http::response(null, 500),
        ]);

        $this->dispatcher()->dispatch(['first' => fn () => throw new Exception('Something went wrong.')]);

        $this->assertTrue(true);
    }

    public function test_resolving_tasks_may_timeout()
    {
        Http::fake([
            '127.0.0.1:8000/octane/resolve-tasks' => Http::response(null, 504),
        ]);

        $this->expectException(TaskTimeoutException::class);
        $this->expectExceptionMessage('Task timed out after 2000 milliseconds.');

        $this->dispatcher()->resolve(['first' => fn () => 1], 2000);
    }

    public function test_resolving_tasks_rejects_responses_not_sealed_as_results()
    {
        $responses = [
            serialize([new WakeupProbe]),
            Crypt::encryptString(serialize([new WakeupProbe])),
            TaskPayload::seal(TaskPayload::generateSecret(), 'resolve-tasks-result', [new WakeupProbe]),
            TaskPayload::seal($this->secret, 'resolve-tasks', [new WakeupProbe]),
        ];

        foreach ($responses as $body) {
            Http::fake([
                '127.0.0.1:8000/octane/resolve-tasks' => Http::response($body),
            ]);

            try {
                $this->dispatcher()->resolve(['first' => fn () => 1]);

                $this->fail('An invalid response was accepted.');
            } catch (TaskException $e) {
                $this->assertSame('Invalid response from task server.', $e->getMessage());
            }
        }

        $this->assertFalse(WakeupProbe::$woken);
    }

    #[DefineEnvironment('bindSwooleServer')]
    public function test_task_routes_run_sealed_tasks()
    {
        $response = $this->callTaskRoute('/octane/resolve-tasks', TaskPayload::seal($this->secret, 'resolve-tasks', [
            'tasks' => [
                'first' => new SerializableClosure(fn () => 1),
                'second' => new SerializableClosure(fn () => 2),
            ],
            'wait' => 1000,
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['first' => 1, 'second' => 2], TaskPayload::open($this->secret, 'resolve-tasks-result', $response->getContent()));

        $response = $this->callTaskRoute('/octane/dispatch-tasks', TaskPayload::seal($this->secret, 'dispatch-tasks', [
            new SerializableClosure(fn () => 3),
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([1, 2, 3], $this->app['Swoole\Http\Server']->results);
    }

    #[DefineEnvironment('bindSwooleServer')]
    public function test_task_routes_reject_payloads_not_sealed_for_them()
    {
        $tasks = [new WakeupProbe];

        $payloads = [
            '/octane/dispatch-tasks' => [
                Crypt::encryptString(serialize($tasks)),
                Crypt::encrypt($tasks),
                TaskPayload::seal(TaskPayload::generateSecret(), 'dispatch-tasks', $tasks),
                TaskPayload::seal($this->secret, 'resolve-tasks', $tasks),
                TaskPayload::seal($this->secret, 'resolve-tasks-result', $tasks),
                'garbage',
                ['not', 'a', 'string'],
                null,
            ],
            '/octane/resolve-tasks' => [
                Crypt::encryptString(serialize(['tasks' => $tasks, 'wait' => 1000])),
                TaskPayload::seal(TaskPayload::generateSecret(), 'resolve-tasks', ['tasks' => $tasks, 'wait' => 1000]),
                TaskPayload::seal($this->secret, 'dispatch-tasks', ['tasks' => $tasks, 'wait' => 1000]),
                TaskPayload::seal($this->secret, 'resolve-tasks-result', ['tasks' => $tasks, 'wait' => 1000]),
                'garbage',
                null,
            ],
        ];

        foreach ($payloads as $uri => $uriPayloads) {
            foreach ($uriPayloads as $payload) {
                $this->assertSame(403, $this->callTaskRoute($uri, $payload)->getStatusCode());
            }
        }

        $this->assertFalse(WakeupProbe::$woken);
        $this->assertSame([], $this->app['Swoole\Http\Server']->results);
    }

    #[DefineEnvironment('bindSwooleServer')]
    public function test_task_routes_reject_expired_payloads()
    {
        $payload = TaskPayload::seal($this->secret, 'dispatch-tasks', [new SerializableClosure(fn () => 1)]);

        $this->travel(TaskPayload::TTL)->seconds();

        $this->assertSame(200, $this->callTaskRoute('/octane/dispatch-tasks', $payload)->getStatusCode());

        $this->travel(1)->seconds();

        $this->assertSame(403, $this->callTaskRoute('/octane/dispatch-tasks', $payload)->getStatusCode());
        $this->assertSame([1], $this->app['Swoole\Http\Server']->results);
    }

    #[DefineEnvironment('bindSwooleServerWithoutSecret')]
    public function test_task_routes_are_not_registered_without_a_task_secret()
    {
        $this->assertFalse(Octane::hasRouteFor('POST', '/octane/resolve-tasks'));
        $this->assertFalse(Octane::hasRouteFor('POST', '/octane/dispatch-tasks'));
    }

    protected function dispatcher()
    {
        return new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher,
            $this->secret,
        );
    }

    protected function callTaskRoute(string $uri, mixed $payload)
    {
        return Octane::invokeRoute(Request::create($uri, 'POST', ['tasks' => $payload]), 'POST', $uri);
    }

    protected function bindSwooleServer($app)
    {
        $this->bindSwooleServerWithoutSecret($app);

        $app->instance('octane.taskSecret', $this->secret);
    }

    protected function bindSwooleServerWithoutSecret($app)
    {
        $app->instance('Swoole\Http\Server', new class
        {
            public array $results = [];

            public function taskWaitMulti(array $tasks, float $timeout)
            {
                return array_map(fn ($task) => new TaskResult($this->results[] = $task()), $tasks);
            }

            public function task($task)
            {
                $this->results[] = $task();
            }
        });
    }

    protected function tearDown(): void
    {
        WakeupProbe::$woken = false;

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return ['Laravel\Octane\OctaneServiceProvider'];
    }
}

class WakeupProbe
{
    public static bool $woken = false;

    public function __wakeup()
    {
        static::$woken = true;
    }
}

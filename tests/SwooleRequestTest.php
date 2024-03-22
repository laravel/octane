<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Laravel\Octane\Swoole\Actions\ConvertSwooleRequestToIlluminateRequest;
use Laravel\Octane\Swoole\SwooleExtension;
use Mockery;
use Orchestra\Testbench\TestCase;
use Swoole\Http\Request as SwooleRequest;

class SwooleRequestTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $extension = new SwooleExtension();

        if (! $extension->isInstalled()) {
            $this->markTestSkipped('Swoole extension is not installed.');
        }
    }

    public function test_convert_swoole_request_to_illuminate_request()
    {
        $swooleRequest = new SwooleRequest();

        $action = new ConvertSwooleRequestToIlluminateRequest();

        $request = $action->__invoke($swooleRequest, 'cli-server');

        $this->assertInstanceOf(Request::class, $request);
    }

    public function test_server_variables()
    {
        $swooleRequest = new SwooleRequest();

        $swooleRequest->server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/foo',
            'QUERY_STRING' => 'bar=baz',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ];

        $swooleRequest->header = [
            'host' => 'localhost',
            'content-type' => 'application/json',
        ];

        $request = (new ConvertSwooleRequestToIlluminateRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('GET', $request->server->get('REQUEST_METHOD'));
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/foo?bar=baz', $request->server->get('REQUEST_URI'));
        $this->assertSame('/foo?bar=baz', $request->getRequestUri());
        $this->assertSame('HTTP/1.1', $request->server->get('SERVER_PROTOCOL'));
        $this->assertSame('HTTP/1.1', $request->getProtocolVersion());
        $this->assertSame('localhost', $request->header('host'));
        $this->assertSame('localhost', $request->getHost());
        $this->assertSame('application/json', $request->header('content-type'));
    }

    public function test_get()
    {
        $swooleRequest = new SwooleRequest();
        $swooleRequest->get = [
            'foo' => 'bar',
        ];

        $request = (new ConvertSwooleRequestToIlluminateRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->get('foo'));
        $this->assertSame('bar', $request->input('foo'));
        $this->assertSame('bar', $request->query('foo'));
    }

    public function test_post()
    {
        $swooleRequest = new SwooleRequest();
        $swooleRequest->header = [
            'content-type' => 'application/x-www-form-urlencoded',
        ];
        $swooleRequest->server = [
            'REQUEST_METHOD' => 'POST',
        ];

        $swooleRequest->post = [
            'foo' => 'bar',
        ];

        $request = (new ConvertSwooleRequestToIlluminateRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->post('foo'));
        $this->assertSame('bar', $request->input('foo'));
    }

    public function test_put()
    {
        $swooleRequest = Mockery::mock(new SwooleRequest())->makePartial();

        $swooleRequest->header = [
            'content-type' => 'application/x-www-form-urlencoded',
        ];
        $swooleRequest->server = [
            'REQUEST_METHOD' => 'PUT',
        ];

        $swooleRequest->shouldReceive('rawContent')->andReturn('foo=bar');

        $request = (new ConvertSwooleRequestToIlluminateRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->input('foo'));
        $this->assertSame('bar', $request->get('foo'));
        $this->assertSame('bar', $request->post('foo'));
    }

    public function test_patch()
    {
        $swooleRequest = Mockery::mock(new SwooleRequest())->makePartial();

        $swooleRequest->header = [
            'content-type' => 'application/x-www-form-urlencoded',
        ];
        $swooleRequest->server = [
            'REQUEST_METHOD' => 'PATCH',
        ];

        $swooleRequest->shouldReceive('rawContent')->andReturn('foo=bar');

        $request = (new ConvertSwooleRequestToIlluminateRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->input('foo'));
        $this->assertSame('bar', $request->get('foo'));
        $this->assertSame('bar', $request->post('foo'));
    }

    public function test_delete()
    {
        $swooleRequest = Mockery::mock(new SwooleRequest())->makePartial();

        $swooleRequest->header = [
            'content-type' => 'application/x-www-form-urlencoded',
        ];
        $swooleRequest->server = [
            'REQUEST_METHOD' => 'DELETE',
        ];

        $swooleRequest->shouldReceive('rawContent')->andReturn('foo=bar');

        $request = (new ConvertSwooleRequestToIlluminateRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->input('foo'));
        $this->assertSame('bar', $request->get('foo'));
        $this->assertSame('bar', $request->post('foo'));
    }

    public function test_cookie()
    {
        $swooleRequest = new SwooleRequest();
        $swooleRequest->cookie = [
            'foo' => 'bar',
        ];

        $request = (new ConvertSwooleRequestToIlluminateRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->cookie('foo'));
    }
}

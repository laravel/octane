<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Exceptions\StaleApplicationException;
use Laravel\Octane\Listeners\ReloadWorkerIfLoadedFilesChanged;
use Laravel\Octane\Octane;

use function Orchestra\Testbench\default_skeleton_path;

class ReloadWorkerIfLoadedFilesChangedTest extends TestCase
{
    public function test_worker_serves_stale_class_when_reload_is_disabled()
    {
        $file = $this->probePath();

        $this->writeProbe($file, 'old');

        try {
            [$app, $worker, $client] = $this->createOctaneContext([
                Request::create('/probe', 'GET'),
                Request::create('/probe', 'GET'),
            ]);

            $app['config']->set('octane.reload_changed_files', false);

            $app['router']->get('/probe', fn () => (new \App\OctaneStaleClassProbe)->value());

            require_once $file;

            $this->writeProbe($file, 'new');
            touch($file, time() + 5);

            $worker->run();

            $this->assertCount(2, $client->responses);
            $this->assertSame('old', $client->responses[0]->getContent());
            $this->assertSame('old', $client->responses[1]->getContent());
            $this->assertCount(0, $client->errors);
        } finally {
            @unlink($file);
        }
    }

    public function test_loaded_class_file_change_throws_instead_of_serving_stale_code()
    {
        $file = $this->probePath();

        $this->writeProbe($file, 'old');

        try {
            [$app] = $this->createOctaneContext([
                Request::create('/probe', 'GET'),
            ]);

            $app['config']->set('octane.reload_changed_files', true);

            require_once $file;

            $listener = $app->make(ReloadWorkerIfLoadedFilesChanged::class);
            $event = new RequestReceived($app, $app, Request::create('/probe', 'GET'));

            $listener->handle($event);

            $this->assertSame('old', (new \App\OctaneStaleClassProbe)->value());

            $this->writeProbe($file, 'new');
            touch($file, time() + 5);

            try {
                $listener->handle($event);
                $this->fail('Expected a stale application exception after the loaded class file changed.');
            } catch (StaleApplicationException $e) {
                $this->assertSame('old', (new \App\OctaneStaleClassProbe)->value());
                $this->assertStringContainsString('stale PHP classes', $e->getMessage());
                $this->assertStringContainsString('Retry the request', $e->getMessage());
                $this->assertContains($file, $e->changedFiles);
                $this->assertSame($e->getMessage(), Octane::formatExceptionForClient($e, false));
            }
        } finally {
            @unlink($file);
        }
    }

    protected function probePath(): string
    {
        return default_skeleton_path().'/app/OctaneStaleClassProbe.php';
    }

    protected function writeProbe(string $file, string $value): void
    {
        file_put_contents($file, <<<PHP
<?php

namespace App;

class OctaneStaleClassProbe
{
    public function value(): string
    {
        return '{$value}';
    }
}
PHP);
    }
}

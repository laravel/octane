<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use Laravel\Octane\Swoole\ServerProcessInspector;
use Laravel\Octane\Swoole\ServerStateFile;
use Laravel\Octane\Swoole\SwooleExtension;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'octane:swoole')]
class StartSwooleCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InteractsWithEnvironmentVariables, Concerns\InteractsWithServers;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:swoole
                    {--host= : The IP address the server should bind to}
                    {--port= : The port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--task-workers=auto : The number of task workers that should be available to handle tasks}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--watch : Automatically reload the server when the application is modified}
                    {--poll : Use file system polling while watching in order to watch files over a network}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Start the Octane Swoole server';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;
    protected bool $isSymlinked = false;
    protected string $dir;
    protected string $real_cwd;
    protected string $pwd;

    public function __construct()
    {
        $this->dir = __DIR__;
        $this->real_cwd = base_path();
        $this->getPwd();

        if ($this->pwd !== $this->real_cwd) {
            $this->isSymlinked = true;
            $this->dir = str_replace($this->real_cwd, $this->pwd, $this->dir);
            app()->setBasePath($this->pwd);

            $log_file = config('octane.swoole.options.log_file');
            if ($log_file) {
                config(['octane.swoole.options.log_file' => $this->realpath($log_file)]);
            }
        }

        parent::__construct();
    }

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle(
        ServerProcessInspector $inspector,
        ServerStateFile $serverStateFile,
        SwooleExtension $extension
    ) {
        if (! $extension->isInstalled()) {
            $this->components->error('The Swoole extension is missing.');

            return 1;
        }

        $this->ensurePortIsAvailable();

        if ($inspector->serverIsRunning()) {
            $this->components->error('Server is already running.');

            return 1;
        }

        if (config('octane.swoole.ssl', false) === true && ! defined('SWOOLE_SSL')) {
            $this->components->error('You must configure Swoole with `--enable-openssl` to support ssl.');

            return 1;
        }

        $this->writeServerStateFile($serverStateFile, $extension);

        $this->forgetEnvironmentVariables();

        $server = tap(new Process([
            (new PhpExecutableFinder)->find(),
            ...config('octane.swoole.php_options', []),
            config('octane.swoole.command', 'swoole-server'),
            $serverStateFile->path(),
        ], $this->realpath($this->dir.'/../../bin'), [
            'APP_ENV' => app()->environment(),
            'APP_BASE_PATH' => base_path(),
            'APP_RELEASE_BIN_DIR' => $this->realpath($this->dir.'/../../bin'),
            'LARAVEL_OCTANE' => 1,
        ]))->start();

        return $this->runServer($server, $inspector, 'swoole');
    }

    /**
     * Write the Swoole server state file.
     *
     * @return void
     */
    protected function writeServerStateFile(
        ServerStateFile $serverStateFile,
        SwooleExtension $extension
    ) {
        $serverStateFile->writeState([
            'appName' => config('app.name', 'Laravel'),
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'workers' => $this->workerCount($extension),
            'taskWorkers' => $this->taskWorkerCount($extension),
            'maxRequests' => $this->option('max-requests'),
            'publicPath' => public_path(),
            'storagePath' => storage_path(),
            'defaultServerOptions' => $this->defaultServerOptions($extension),
            'octaneConfig' => config('octane'),
        ]);
    }

    /**
     * Get the default Swoole server options.
     *
     * @return array
     */
    protected function defaultServerOptions(SwooleExtension $extension)
    {
        return [
            'enable_coroutine' => false,
            'daemonize' => false,
            'log_file' => storage_path('logs/swoole_http.log'),
            'log_level' => app()->environment('local') ? SWOOLE_LOG_INFO : SWOOLE_LOG_ERROR,
            'max_request' => $this->option('max-requests'),
            'package_max_length' => 10 * 1024 * 1024,
            'reactor_num' => $this->workerCount($extension),
            'send_yield' => true,
            'socket_buffer_size' => 10 * 1024 * 1024,
            'task_max_request' => $this->option('max-requests'),
            'task_worker_num' => $this->taskWorkerCount($extension),
            'worker_num' => $this->workerCount($extension),
        ];
    }

    protected function realpath(string $file): string
    {
        $realPath = realpath($file);

        if (! $this->isSymlinked) {
            return $realPath;
        }

        return str_replace($this->real_cwd, $this->pwd, $realPath);
    }

    protected function getPwd(): void
    {
        $working_dir = dirname(request()->server('SCRIPT_NAME'));
        if (($starts_as_curr = str_starts_with($working_dir, './')) || str_starts_with($working_dir, '/')) {
            if ($starts_as_curr) {
                $working_dir = substr($working_dir, 2);
            }

            $cwd = getcwd();
            if (! str_starts_with($cwd, $working_dir)) {
                $this->pwd = "$cwd/$working_dir";
            } else {
                $this->pwd = $working_dir;
            }
        } elseif ($working_dir === '.') {
            // This part comes into action only if the server changes to
            // the base path directory before running the Artisan command.
            // eg. cd /www/current && php artisan octane:start
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $this->pwd = trim(shell_exec('cd')); // For Windows
            } else {
                $this->pwd = trim(shell_exec('pwd')); // For Unix-like systems
            }
        } else {
            $this->pwd = $this->real_cwd;
        }
    }

    /**
     * Get the number of workers that should be started.
     *
     * @return int
     */
    protected function workerCount(SwooleExtension $extension)
    {
        return $this->option('workers') === 'auto'
                    ? $extension->cpuCount()
                    : $this->option('workers');
    }

    /**
     * Get the number of task workers that should be started.
     *
     * @return int
     */
    protected function taskWorkerCount(SwooleExtension $extension)
    {
        return $this->option('task-workers') === 'auto'
                    ? $extension->cpuCount()
                    : $this->option('task-workers');
    }

    /**
     * Write the server process output ot the console.
     *
     * @param  \Symfony\Component\Process\Process  $server
     * @return void
     */
    protected function writeServerOutput($server)
    {
        [$output, $errorOutput] = $this->getServerOutput($server);

        Str::of($output)
            ->explode("\n")
            ->filter()
            ->each(fn ($output) => is_array($stream = json_decode($output, true))
                ? $this->handleStream($stream)
                : $this->components->info($output)
            );

        Str::of($errorOutput)
            ->explode("\n")
            ->filter()
            ->groupBy(fn ($output) => $output)
            ->each(function ($group) {
                is_array($stream = json_decode($output = $group->first(), true)) && isset($stream['type'])
                    ? $this->handleStream($stream)
                    : $this->raw($output);
            });
    }

    /**
     * Stop the server.
     *
     * @return void
     */
    protected function stopServer()
    {
        $this->callSilent('octane:stop', [
            '--server' => 'swoole',
        ]);
    }
}

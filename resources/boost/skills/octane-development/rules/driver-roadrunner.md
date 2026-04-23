# RoadRunner Driver

> **Applies to:** RoadRunner driver only (`config('octane.server') === 'roadrunner'`)

## What is RoadRunner?

RoadRunner is a high-performance PHP application server written in Go. It communicates with PHP workers over a Unix socket using a binary protocol, making it faster than traditional PHP-FPM. It supports PSR-7, middleware pipelines, and has an extensive plugin ecosystem.

## Installation

```bash
composer require laravel/octane
php artisan octane:install --server=roadrunner
```

This downloads the RoadRunner binary (`rr`) and generates a `.rr.yaml` configuration file.

## Starting the Server

```bash
# Development
php artisan octane:start --server=roadrunner

# With specific settings
php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8080 --workers=4

# Watch mode (auto-reload on file changes)
php artisan octane:start --server=roadrunner --watch
```

## .rr.yaml Configuration

The `.rr.yaml` at your project root configures the RoadRunner server:

```yaml
version: "3"

rpc:
  listen: tcp://127.0.0.1:6001

server:
  command: "php artisan octane:start --server=roadrunner --port=8000 --rr-config=.rr.yaml"

http:
  address: 0.0.0.0:8000
  middleware: [gzip]
  pool:
    num_workers: 4
    max_jobs: 500       # Requests per worker before recycling
    supervisor:
      max_worker_memory: 128  # MB — kill worker if it exceeds this

logs:
  mode: development
  level: debug
```

### Key Configuration Options

- **`pool.num_workers`**: Number of PHP worker processes
- **`pool.max_jobs`**: Requests per worker before it restarts (similar to `max-requests` in FrankenPHP)
- **`supervisor.max_worker_memory`**: Memory limit in MB — worker is killed and restarted if exceeded
- **`http.middleware`**: HTTP-level middleware (gzip compression, etc.)

## Runtime Detection

Detect RoadRunner at runtime:

```php
// Via config (most reliable — set by Octane)
config('octane.server') === 'roadrunner'

// Via environment variable set by RoadRunner
getenv('RR_MODE') === 'http'

// Via PSR-7 check — RoadRunner uses PSR-7 internally
// (not directly detectable from user code)
```

## PSR-7 and PSR-15

RoadRunner uses PSR-7 request/response objects internally. Octane bridges these to Laravel's Illuminate HTTP objects automatically. You do not need to use PSR-7 directly in your application code.

However, if you write custom RoadRunner middleware or workers, be aware:

```php
// RoadRunner passes PSR-7 ServerRequestInterface to workers
// Octane converts to Illuminate\Http\Request automatically
// You work with regular Laravel Request objects in controllers/middleware
```

## Updating the Binary

```bash
./rr get-binary
```

Or update via Composer:
```bash
composer update spiral/roadrunner-laravel
php artisan octane:install --server=roadrunner
```

## Worker Reload

```bash
php artisan octane:reload   # Graceful reload of all workers
php artisan octane:stop     # Stop the server
php artisan octane:status   # Check server status
```

## Feature Comparison vs. Other Drivers

| Feature | RoadRunner | FrankenPHP | Swoole |
|---------|-----------|-----------|--------|
| Written in | Go | Go | C (PHP ext) |
| Protocol | Binary (gRPC-like) | Caddy HTTP | Swoole native |
| PSR-7 | ✅ (internal) | ✅ (internal) | ❌ |
| HTTP/2 native | ❌ (via proxy) | ✅ | ❌ (via proxy) |
| Plugin ecosystem | ✅ (RR plugins) | ✅ (Caddy plugins) | ❌ |
| `Octane::concurrently()` | ❌ | ❌ | ✅ |
| Swoole tables | ❌ | ❌ | ✅ |
| Windows support | ✅ | ✅ | ❌ |

## Common Issues

**Binary not found**: Ensure `./rr` (or `rr.exe` on Windows) is in your project root and is executable: `chmod +x ./rr`.

**Workers not restarting**: Use `php artisan octane:reload` or restart the server.

**Memory leaks in workers**: Lower `pool.max_jobs` or set `supervisor.max_worker_memory` in `.rr.yaml`.

**Windows development**: RoadRunner works on Windows natively (unlike Swoole). The binary is `rr.exe` and `.rr.yaml` is the same format.

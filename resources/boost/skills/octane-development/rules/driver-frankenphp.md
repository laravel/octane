# FrankenPHP Driver

> **Applies to:** FrankenPHP driver only (`config('octane.server') === 'frankenphp'`)

## What is FrankenPHP?

FrankenPHP is a modern PHP app server written in Go, built on top of the Caddy web server. It embeds PHP directly, supports HTTP/2 and HTTP/3 out of the box, and is the default recommended driver for new Octane projects.

## Installation

```bash
composer require laravel/octane
php artisan octane:install --server=frankenphp
```

This installs the FrankenPHP binary and creates a `Caddyfile` in your project root.

## Starting the Server

```bash
# Development
php artisan octane:start --server=frankenphp

# With specific host/port
php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8080

# With HTTPS (uses Caddy's automatic TLS)
php artisan octane:start --server=frankenphp --https

# With HTTP/3
php artisan octane:start --server=frankenphp --http2 --https
```

## Caddyfile Configuration

The Caddyfile at your project root configures the Caddy server:

```caddyfile
{
    frankenphp
    # Global options
}

localhost {
    root * public/
    encode zstd br gzip

    php_server {
        worker ../artisan octane:frankenphp-worker --max-requests=1000
    }
}
```

### Key Caddyfile Options

- **`php_server`**: Handles PHP requests via FrankenPHP workers
- **`worker`**: Launches Laravel Octane in worker mode (long-running, handles many requests)
- **`max-requests`**: Number of requests each worker handles before being recycled
- **`encode`**: Response compression (zstd, brotli, gzip)
- **`root * public/`**: Must point to your Laravel `public/` directory

## Docker Deployment

FrankenPHP has an official Docker image that bundles everything:

```dockerfile
FROM dunglas/frankenphp

COPY . /app
WORKDIR /app

RUN composer install --no-dev --optimize-autoloader
RUN php artisan config:cache && php artisan route:cache

CMD ["php", "artisan", "octane:frankenphp-worker", "--max-requests=1000"]
```

Or use the Octane-specific Docker image pattern:

```dockerfile
FROM dunglas/frankenphp

COPY . /app
WORKDIR /app

EXPOSE 80 443 443/udp

CMD ["frankenphp", "run", "--config", "Caddyfile"]
```

## Runtime Detection

Detect FrankenPHP at runtime:

```php
// Via config (most reliable — set by Octane)
config('octane.server') === 'frankenphp'

// Via PHP function (available when running inside FrankenPHP)
function_exists('frankenphp_handle_request')
```

## HTTP/2 and HTTP/3

FrankenPHP (via Caddy) supports HTTP/2 by default with TLS and HTTP/3 (QUIC) when enabled:

```caddyfile
{
    frankenphp
}

https://myapp.com {
    # HTTP/2 is automatic with TLS
    # HTTP/3 is enabled via:
    servers {
        protocols h1 h2 h3
    }

    root * public/
    php_server
}
```

## Early Hints (103 Early Hints)

FrankenPHP supports early hints for preloading assets before the full response:

```php
use Symfony\Component\HttpFoundation\Response;

// In a middleware or controller
header('Link: </css/app.css>; rel=preload; as=style', false, 103);
header('Link: </js/app.js>; rel=preload; as=script', false, 103);
```

## Feature Comparison vs. Other Drivers

| Feature | FrankenPHP | Swoole | RoadRunner |
|----------|------------|--------|------------|
| HTTP/2 native | ✅ | ❌ (via proxy) | ❌ (via proxy) |
| HTTP/3 / QUIC | ✅ | ❌ | ❌ |
| Early hints (103) | ✅ | ❌ | ❌ |
| Automatic TLS | ✅ (Caddy) | ❌ | ❌ |
| `Octane::concurrently()` | ❌ | ✅ | ❌ |
| Swoole tables | ❌ | ✅ | ❌ |
| Go-based binary | ❌ | ❌ | ✅ |
| Pure PHP extension | ❌ | ✅ | ❌ |

## Common Issues

**Worker not restarting after code change**: Run `php artisan octane:reload` or use `--watch` in development.

**Port 443 permission denied**: On Linux, run with elevated permissions or use Caddy's `bind` directive with a non-privileged port and reverse proxy.

**SSL certificate issues in development**: FrankenPHP uses Caddy's automatic HTTPS with Let's Encrypt. For localhost, it uses a self-signed certificate — trust it in your browser or OS.

**Worker mode not detected when Caddyfile path is a symlink**: FrankenPHP may fail to detect worker mode if the `root` path in your Caddyfile resolves through a symlink. This is a known FrankenPHP gotcha — it checks the resolved filesystem path, not the symlink target.

**Symptom**: You see `FrankenPHP must be in worker mode to use this script` even though your Caddyfile has a `worker` directive.

**Fix**: Use the real absolute path in your Caddyfile's `root` directive instead of a symlinked path:

```bash
# Check if your project root is a symlink
readlink your-project-dir

# If it is, use the resolved path in Caddyfile:
root * /real/absolute/path/to/public/
```

Or resolve the symlink when starting:

```bash
php artisan octane:start --server=frankenphp --caddyfile=$(realpath Caddyfile)
```

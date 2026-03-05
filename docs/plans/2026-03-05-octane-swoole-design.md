# Laravel Octane + Swoole Design

**Date:** 2026-03-05
**Status:** Approved

## Context

The SurpriseMoi backend runs Laravel 12 on PHP 8.3 inside a Docker container using PHP-FPM + Nginx. We are replacing PHP-FPM with Laravel Octane powered by Swoole for maximum performance. The app boots once and stays in memory, serving requests at high speed.

**Current stack:** PHP 8.3-fpm-alpine, Nginx, Supervisor (FPM + Nginx + Reverb), PostgreSQL 16, Redis 7.
**Target stack:** PHP 8.3-cli-alpine, Swoole, Nginx (reverse proxy), Supervisor (Octane + Nginx + Reverb).

## Decisions

- **Server:** Swoole (full Octane feature set: concurrent tasks, ticks, intervals, Swoole tables/cache)
- **Nginx:** Stays inside the Docker container, proxying dynamic requests to Octane on port 8000 and serving static files directly
- **Reverb:** Keeps running as a separate Supervisor process on port 8080
- **Queue workers:** Stay external on the VPS host via `docker exec`
- **VPS host Nginx:** No changes needed (already reverse-proxies to container port 8082)

## Architecture

```
[Client] → [VPS Nginx :443 SSL] → [Docker :8082] → [Container Nginx :80]
                                                        ├── Static files → served directly
                                                        └── Dynamic → proxy_pass → [Octane/Swoole :8000]

[Reverb :8080] ← WebSocket connections (proxied through both Nginx layers)
[Queue workers] ← VPS host Supervisor via docker exec
```

## Docker Image Changes

### Base Image
- **From:** `php:8.3-fpm-alpine`
- **To:** `php:8.3-cli-alpine`

### Swoole Installation
```dockerfile
RUN apk add --no-cache $PHPIZE_DEPS linux-headers openssl-dev curl-dev \
    && pecl install swoole --enable-sockets --enable-openssl --enable-curl \
    && docker-php-ext-enable swoole \
    && apk del $PHPIZE_DEPS linux-headers
```

### OPcache JIT
Disable JIT (conflicts with Swoole), keep OPcache file caching:
```ini
opcache.jit=disable
; Remove opcache.jit_buffer_size entirely
```

### Removed
- PHP-FPM config files (`www.conf`, `zz-docker.conf`)
- PHP-FPM related Supervisor program

## Nginx Configuration (In-Container)

Replace FastCGI proxy with HTTP reverse proxy to Octane:

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

upstream octane {
    server 127.0.0.1:8000;
}

server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;
    charset utf-8;
    client_max_body_size 64M;

    # Static assets served by Nginx
    location /build/ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Reverb WebSocket
    location /app/ {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_pass http://127.0.0.1:8080;
    }

    # All other requests → Octane
    location / {
        try_files $uri $uri/ @octane;
    }

    location @octane {
        set $suffix "";
        if ($uri = /index.php) {
            set $suffix ?$query_string;
        }

        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_pass http://octane$suffix;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Supervisor Configuration

Replace `[program:php-fpm]` with:
```ini
[program:octane]
process_name=%(program_name)s
command=php /var/www/html/artisan octane:start --server=swoole --host=127.0.0.1 --port=8000 --max-requests=500
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/octane.log
stopwaitsecs=3600
priority=5
```

Nginx, Reverb, and queue-worker programs remain unchanged.

## Memory Leak Prevention

### Code Fixes Required

**1. `app/Http/Resources/ProductResource.php`** — static `$wishlistProductIds` array:
- Flush in Octane's `RequestReceived` listener
- Or refactor to request-scoped state

**2. `app/Http/Resources/ServiceResource.php`** — static `$wishlistServiceIds` array:
- Same fix as above

### Octane Listeners (`config/octane.php`)
```php
'listeners' => [
    RequestReceived::class => [
        // Flush static caches on each request
        fn () => ProductResource::flushWishlistCache(),
        fn () => ServiceResource::flushWishlistCache(),
    ],
],
```

### Safety Nets
- `--max-requests=500`: Workers recycle after 500 requests
- Monitor worker memory via `php artisan octane:status`

### What Is Already Safe
- `AppServiceProvider` uses `bind()` not `singleton()` — clean
- No constructor-injected `Request` or `Container` in services
- `PaystackService` uses `request()` helper (safe in Octane)
- `Setting` model uses Redis-backed `Cache::remember()` — no in-process state

## Octane Configuration Highlights

```php
// config/octane.php
'server' => env('OCTANE_SERVER', 'swoole'),
'https' => env('OCTANE_HTTPS', false),
'max_execution_time' => 30,
'workers' => env('OCTANE_WORKERS', 'auto'),
'task_workers' => env('OCTANE_TASK_WORKERS', 6),
'max_requests' => env('OCTANE_MAX_REQUESTS', 500),
```

## Entrypoint Changes

- Keep: `route:cache`, `view:cache`, `migrate --force`, `storage:link`
- Keep: `config:cache` (Octane reads cached config on boot — this is fine)

## Testing Strategy

1. **Existing test suite** — run full suite after installation (tests use standard PHP, not Swoole)
2. **Docker build and smoke test** — build container, hit key endpoints
3. **Memory monitoring** — `docker stats` + `php artisan octane:status`
4. **Static asset verification** — confirm Nginx serves CSS/JS directly
5. **WebSocket verification** — confirm Reverb works alongside Octane
6. **Queue worker verification** — confirm `docker exec` queue workers still function

## Risks and Mitigations

| Risk | Mitigation |
|------|-----------|
| Memory leaks from static state | Flush in `RequestReceived` + `--max-requests=500` |
| OPcache JIT conflict with Swoole | Disable JIT, keep file caching |
| Stale singleton state across requests | Octane resets first-party state automatically; our bindings are transient |
| Swoole build time in Docker | Multi-stage build, extension compiled once |
| Reverb conflict with Swoole | Separate processes via Supervisor |

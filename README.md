# SurpriseMoi Backend

Laravel API and Inertia/React dashboard for the SurpriseMoi marketplace: vendors, customers, riders, field agents, referrals, payouts, and admin operations.

## Stack

- PHP 8.3 / Laravel 13
- Inertia v2 + React 19 + Vite (bun)
- PostgreSQL 16 (pgvector), Redis
- Laravel Sanctum, Fortify, Horizon, Octane, Reverb

## Local development

```bash
docker compose up -d
composer install
bun install
php artisan migrate
composer dev
```

`composer dev` runs the app server, queue worker, Reverb, and Vite together.

## Tests

```bash
php artisan test
```

## Deployment

```bash
./deploy.sh
```

Builds the production image on the host and performs a zero-downtime blue-green switch (see the script header for details).

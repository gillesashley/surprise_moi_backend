#!/usr/bin/env bash
# =============================================================================
# SurpriseMoi Backend - VPS deploy
#
# Builds the production Docker image directly on this host (no GHCR / registry)
# and rolls the stack. Everything is compiled during the image build:
#   - PHP dependencies            (composer install --no-dev)
#   - Wayfinder TypeScript routes (php artisan wayfinder:generate)
#   - Frontend assets             (bun run build -> public/build)
#   - Runtime image               (php:8.3-cli-alpine + nginx + supervisor)
#
# On boot the container entrypoint handles config/route/view caching,
# migrations (--force) and the storage symlink.
#
# Usage: ./deploy.sh [--no-cache]
# =============================================================================
set -euo pipefail

cd "$(dirname "$0")"

if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: docker is not installed." >&2
  exit 1
fi

# Use only the base compose file: docker-compose.override.yml is for local dev
# and would switch to the development stage / .env.local.
if docker compose version >/dev/null 2>&1; then
  DC=(docker compose -f docker-compose.yml)
elif command -v docker-compose >/dev/null 2>&1; then
  DC=(docker-compose -f docker-compose.yml)
else
  echo "ERROR: docker compose is not available." >&2
  exit 1
fi

if [ ! -f .env ]; then
  echo "ERROR: .env is missing (required by the app service)." >&2
  exit 1
fi

BUILD_ARGS=()
if [ "${1:-}" = "--no-cache" ]; then
  BUILD_ARGS+=(--no-cache)
fi

echo "==> Building production image on this host (composer + wayfinder + bun build)"
"${DC[@]}" build "${BUILD_ARGS[@]}" --pull app

echo "==> Starting services"
"${DC[@]}" up -d --remove-orphans --no-build

echo "==> Waiting for the app to become healthy"
for i in $(seq 1 60); do
  status="$(docker inspect -f "{{.State.Health.Status}}" surprisemoi-app 2>/dev/null || echo starting)"
  if [ "$status" = "healthy" ]; then
    echo "    app is healthy"
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "ERROR: app did not become healthy. Recent logs:" >&2
    "${DC[@]}" logs --tail=80 app >&2 || true
    exit 1
  fi
  sleep 5
done

# Restart a host-level queue worker if supervisor manages one.
if command -v supervisorctl >/dev/null 2>&1 && supervisorctl status surprisemoi-queue >/dev/null 2>&1; then
  echo "==> Restarting host queue worker"
  supervisorctl restart surprisemoi-queue || true
fi

echo "==> Pruning dangling images"
docker image prune -f >/dev/null 2>&1 || true

echo "==> Deploy complete"

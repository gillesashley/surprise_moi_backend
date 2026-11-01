#!/usr/bin/env bash
# =============================================================================
# SurpriseMoi Backend - blue-green deploy (build on the VPS, no GHCR)
#
# Each deploy:
#   1. Builds the production image on this host (composer + ok Wayfinder + bun build)
#   2. Starts the idle slot (app-blue / app-green) on the shared docker network
#   3. Waits until the new slot reports healthy
#   4. Rewrites the router upstream and gracefully reloads nginx (no dropped requests)
#   5. Stops the previous slot after a short drain window
#
# The host nginx keeps proxying to 127.0.0.1:8082 (the router), so it never
# needs to change and the public site is never down for HTTP traffic.
#
# Sessions/cache/queue live in Redis, so switching slots keeps users logged in.
#
# Usage: ./deploy.sh [--no-cache]
# =============================================================================
set -euo pipefail

cd "$(dirname "$0")"

COMPOSE_FILE="docker-compose.prod.yml"
STATE_DIR=".deploy"
ACTIVE_FILE="${STATE_DIR}/active"
UPSTREAM_FILE="${STATE_DIR}/router/upstream.conf"
DRAIN_SECONDS="${DRAIN_SECONDS:-15}"

die() { echo "ERROR: $*" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || die "docker is not installed"
docker compose version >/dev/null 2>&1 || die "docker compose v2 is required"
[ -f .env ] || die ".env is missing"

BUILD_ARGS=()
[ "${1:-}" = "--no-cache" ] && BUILD_ARGS+=(--no-cache)

DC=(docker compose -f "$COMPOSE_FILE" --env-file .env)

TAG="$(git rev-parse --short HEAD 2>/dev/null || date +%s)"
IMAGE="surprisemoi-app:${TAG}"
export APP_IMAGE="$IMAGE"

mkdir -p "${STATE_DIR}/router"

ACTIVE="$(cat "$ACTIVE_FILE" 2>/dev/null || echo none)"
if [ "$ACTIVE" = "blue" ]; then TARGET="green"; else TARGET="blue"; fi

echo "==> Active slot: ${ACTIVE}; deploying to: ${TARGET} (${IMAGE})"

echo "==> Building production image on this host"
docker build "${BUILD_ARGS[@]}" -t "$IMAGE" --target production .

echo "==> Ensuring database and redis are running"
"${DC[@]}" up -d --no-build db redis

echo "==> Starting ${TARGET} slot"
"${DC[@]}" up -d --no-build --no-deps "app-${TARGET}"

wait_healthy() {
  local name="$1" i status
  for i in $(seq 1 60); do
    status="$(docker inspect -f "{{.State.Health.Status}}" "$name" 2>/dev/null || echo missing)"
    if [ "$status" = "healthy" ]; then
      echo "    ${name} is healthy"
      return 0
    fi
    sleep 5
  done
  echo "ERROR: ${name} did not become healthy" >&2
  docker logs --tail 80 "$name" >&2 || true
  return 1
}

wait_healthy "surprisemoi-app-${TARGET}"

echo "==> Switching router upstream to ${TARGET}"
printf "upstream backend {\n    server app-%s:80;\n}\n" "$TARGET" > "$UPSTREAM_FILE"

# First cut-over from the old single-container stack: free :8082 for the router.
if docker ps -a --format "{{.Names}}" | grep -qx "surprisemoi-app"; then
  echo "==> Removing legacy single-container app (frees :8082 for the router)"
  docker rm -f surprisemoi-app >/dev/null 2>&1 || true
fi

"${DC[@]}" up -d --no-build --no-deps router
for i in $(seq 1 20); do
  docker exec surprisemoi-router true >/dev/null 2>&1 && break
  sleep 1
done
docker exec surprisemoi-router nginx -t
docker exec surprisemoi-router nginx -s reload

echo "$TARGET" > "$ACTIVE_FILE"
echo "==> Traffic is now served by ${TARGET}"

if [ "$ACTIVE" != "none" ] && [ "$ACTIVE" != "$TARGET" ]; then
  echo "==> Draining ${ACTIVE} for ${DRAIN_SECONDS}s, then stopping it"
  sleep "$DRAIN_SECONDS"
  "${DC[@]}" stop "app-${ACTIVE}" >/dev/null
fi

echo "==> Pruning dangling images"
docker image prune -f >/dev/null 2>&1 || true

echo "==> Deploy complete. Active slot: ${TARGET}. Rollback: set /etc -> previous image tag."

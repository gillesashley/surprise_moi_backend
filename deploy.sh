#!/usr/bin/env bash
set -euo pipefail

IMAGE="ghcr.io/gillesashley/surprise_moi_backend:main-latest"

echo "Pulling image: ${IMAGE}"
docker pull "${IMAGE}"

echo "Image pulled successfully: ${IMAGE}"

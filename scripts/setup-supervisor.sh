#!/bin/bash

# =============================================================================
# SurpriseMoi - Supervisor Setup Script
# Run this on your VPS to set up Supervisor for queue workers and scheduler
# =============================================================================

set -e

echo "=== SurpriseMoi Supervisor Setup ==="
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then 
    echo "Please run with sudo: sudo bash scripts/setup-supervisor.sh"
    exit 1
fi

# Define paths
PROJECT_DIR="/var/www/surprise_moi"
SUPERVISOR_CONF_DIR="/etc/supervisor/conf.d"

echo "1. Copying Supervisor configuration files..."
cp "$PROJECT_DIR/docker/supervisor/surprisemoi-queue.conf" "$SUPERVISOR_CONF_DIR/"
cp "$PROJECT_DIR/docker/supervisor/surprisemoi-scheduler.conf" "$SUPERVISOR_CONF_DIR/"

echo "2. Creating log directory if it doesn't exist..."
mkdir -p "$PROJECT_DIR/storage/logs"
chown -R deploy:deploy "$PROJECT_DIR/storage/logs"

echo "3. Reloading Supervisor configuration..."
supervisorctl reread
supervisorctl update

echo "4. Starting SurpriseMoi services..."
supervisorctl start surprisemoi-queue:*
supervisorctl start surprisemoi-scheduler:*

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Check status with:"
echo "  sudo supervisorctl status"
echo ""
echo "View logs with:"
echo "  tail -f $PROJECT_DIR/storage/logs/queue-worker.log"
echo "  tail -f $PROJECT_DIR/storage/logs/scheduler.log"
echo ""
echo "Restart services with:"
echo "  sudo supervisorctl restart surprisemoi-queue:*"
echo "  sudo supervisorctl restart surprisemoi-scheduler:*"

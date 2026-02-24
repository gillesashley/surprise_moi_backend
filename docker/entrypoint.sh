#!/bin/sh
# =============================================================================
# Docker Entrypoint Script for SurpriseMoi
# =============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Create required directories
log_info "Creating required directories..."
mkdir -p /var/log/nginx
mkdir -p /var/log/php-fpm
mkdir -p /var/log/supervisor
mkdir -p /var/run/php
mkdir -p /var/www/html/storage/framework/{sessions,views,cache}
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache
mkdir -p /var/www/html/storage/app/public

# Set permissions
log_info "Setting permissions..."
chown -R www-data:www-data /var/run/php
chmod 755 /var/run/php
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Wait for database to be ready (if applicable)
if [ ! -z "$DB_HOST" ]; then
    log_info "Waiting for PostgreSQL database to be ready..."
    max_attempts=30
    attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        if php -r "
            \$host = getenv('DB_HOST');
            \$port = getenv('DB_PORT') ?: 5432;
            \$conn = @fsockopen(\$host, \$port, \$errno, \$errstr, 5);
            if (\$conn) {
                fclose(\$conn);
                exit(0);
            }
            exit(1);
        "; then
            log_info "Database is ready!"
            break
        fi
        
        attempt=$((attempt + 1))
        log_warn "Database not ready yet (attempt $attempt/$max_attempts)..."
        sleep 2
    done
    
    if [ $attempt -eq $max_attempts ]; then
        log_error "Database connection failed after $max_attempts attempts"
        exit 1
    fi
fi

# Run Laravel optimizations (only for app role)
if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    log_info "Running Laravel optimizations..."

    # Clear and cache configuration
    php artisan config:clear 2>/dev/null || true
    php artisan config:cache

    # Cache routes
    php artisan route:clear 2>/dev/null || true
    php artisan route:cache

    # Cache views
    php artisan view:clear 2>/dev/null || true
    php artisan view:cache

    # Run migrations (with force flag for production)
    log_info "Running database migrations..."
    php artisan migrate --force

    # Create storage link if it doesn't exist
    if [ ! -L "/var/www/html/public/storage" ]; then
        log_info "Creating storage symlink..."
        php artisan storage:link
    fi

    log_info "Laravel optimizations completed!"
fi

# Queue worker role - run migrations and cache clear
if [ "${CONTAINER_ROLE:-app}" = "queue" ]; then
    log_info "Running Laravel queue worker setup..."

    # Clear and cache configuration
    php artisan config:clear 2>/dev/null || true
    php artisan config:cache

    # Run migrations if needed
    log_info "Checking database migrations..."
    php artisan migrate --force

    log_info "Queue worker setup completed!"
fi

# Execute the main command
log_info "Starting application..."
exec "$@"

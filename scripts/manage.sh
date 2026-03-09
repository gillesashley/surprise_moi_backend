#!/bin/bash
# =============================================================================
# SurpriseMoi Backend - Intelligent Deployment Manager
# A single, smart script that handles all deployment operations
# =============================================================================

set -e

# Configuration - use $0 for portability
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"
ENV_FILE="$PROJECT_DIR/.env"
ENV_DOCKER="$PROJECT_DIR/.env.docker"
BACKUP_DIR="$PROJECT_DIR/backups"
DOMAIN="dashboard.surprisemoi.com"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Logging functions
log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_step() { echo -e "${BLUE}[STEP]${NC} $1"; }
log_action() { echo -e "${CYAN}[ACTION]${NC} $1"; }

# Print banner
print_banner() {
    echo -e "${GREEN}"
    echo "╔═══════════════════════════════════════════════════════════════╗"
    echo "║        SurpriseMoi Intelligent Deployment Manager             ║"
    echo "║                  dashboard.surprisemoi.com                    ║"
    echo "╚═══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

# =============================================================================
# SMART DETECTION FUNCTIONS
# =============================================================================

# Check if Docker is installed and running
check_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        return 1
    fi
    if ! docker info >/dev/null 2>&1; then
        return 2
    fi
    return 0
}

# Check if services are running
services_running() {
    cd "$PROJECT_DIR"
    if docker compose ps --status running 2>/dev/null | grep -q "surprisemoi"; then
        return 0
    fi
    return 1
}

# Check if .env exists and is configured
env_configured() {
    if [ -f "$ENV_FILE" ]; then
        if grep -q "DB_HOST=db" "$ENV_FILE"; then
            return 0
        fi
    fi
    return 1
}

# Check if images are built
images_built() {
    if docker images | grep -q "surprise_moi_backend"; then
        return 0
    fi
    return 1
}

# Check if database has data
database_has_data() {
    cd "$PROJECT_DIR"
    if docker compose exec -T db psql -U laraveluser -d surprise_moi_db -c "SELECT COUNT(*) FROM migrations;" >/dev/null 2>&1; then
        return 0
    fi
    return 1
}

# =============================================================================
# CORE OPERATIONS
# =============================================================================

# Initial setup
do_setup() {
    log_step "Running initial setup..."
    
    # Make script executable
    chmod +x "$SCRIPT_DIR"/*.sh 2>/dev/null || true
    
    # Create directories
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$PROJECT_DIR/storage/app/public"
    mkdir -p "$PROJECT_DIR/storage/framework"/{sessions,views,cache}
    mkdir -p "$PROJECT_DIR/storage/logs"
    mkdir -p "$PROJECT_DIR/bootstrap/cache"
    
    # Setup environment file
    if [ ! -f "$ENV_FILE" ]; then
        if [ -f "$ENV_DOCKER" ]; then
            cp "$ENV_DOCKER" "$ENV_FILE"
            log_info "Created .env from .env.docker"
        else
            log_error "No .env.docker found. Please create it first."
            exit 1
        fi
    fi
    
    # Set permissions
    chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache" 2>/dev/null || true
    
    log_info "Setup completed!"
}

# Build images
do_build() {
    local fresh="${1:-}"
    log_step "Building Docker images..."
    
    cd "$PROJECT_DIR"
    
    if [ "$fresh" = "--fresh" ] || [ "$fresh" = "-f" ]; then
        log_info "Building fresh images (no cache)..."
        docker compose build --no-cache
    else
        docker compose build
    fi
    
    log_info "Images built successfully!"
}

# Start services
do_start() {
    log_step "Starting services..."
    
    cd "$PROJECT_DIR"
    docker compose up -d
    
    log_info "Waiting for services to initialize..."
    sleep 15
    
    # Check health
    local retries=0
    while [ $retries -lt 30 ]; do
        if docker compose ps | grep -q "healthy"; then
            break
        fi
        sleep 2
        retries=$((retries + 1))
    done
    
    log_info "Services started!"
}

# Stop services
do_stop() {
    log_step "Stopping services..."
    cd "$PROJECT_DIR"
    docker compose down
    log_info "Services stopped!"
}

# Run Laravel optimizations
do_optimize() {
    log_step "Running Laravel optimizations..."
    
    cd "$PROJECT_DIR"
    
    # Generate key if not set
    if ! grep -q "APP_KEY=base64:" "$ENV_FILE"; then
        log_info "Generating application key..."
        docker compose exec -T app php artisan key:generate --force
    fi
    
    # Clear and cache
    docker compose exec -T app php artisan config:clear
    docker compose exec -T app php artisan config:cache
    docker compose exec -T app php artisan route:clear
    docker compose exec -T app php artisan route:cache
    docker compose exec -T app php artisan view:clear
    docker compose exec -T app php artisan view:cache
    
    log_info "Optimizations completed!"
}

# Run migrations
do_migrate() {
    log_step "Running database migrations..."
    cd "$PROJECT_DIR"
    docker compose exec -T app php artisan migrate --force
    log_info "Migrations completed!"
}

# Build frontend assets
do_build_frontend() {
    log_step "Building frontend assets..."
    
    cd "$PROJECT_DIR"
    
    # Check if composer dependencies exist (required for Wayfinder plugin)
    if [ ! -d "$PROJECT_DIR/vendor" ]; then
        log_info "Installing Composer dependencies (required for frontend build)..."
        if command -v composer >/dev/null 2>&1; then
            composer install --no-dev --optimize-autoloader
        else
            log_warn "Composer not found on host. Installing via Docker..."
            docker compose run --rm app composer install --no-dev --optimize-autoloader
        fi
    fi
    
    # Check if pnpm is installed
    if ! command -v pnpm >/dev/null 2>&1; then
        log_warn "pnpm not found. Installing pnpm..."
        npm install -g pnpm
    fi
    
    # Install dependencies if node_modules doesn't exist
    if [ ! -d "$PROJECT_DIR/node_modules" ]; then
        log_info "Installing frontend dependencies..."
        pnpm install
    fi
    
    # Generate Wayfinder types (required — these files are gitignored)
    log_info "Generating Wayfinder route types..."
    local wayfinder_generated=false

    if services_running; then
        if docker compose exec -T app php artisan wayfinder:generate --with-form 2>/dev/null; then
            wayfinder_generated=true
        fi
    fi

    if [ "$wayfinder_generated" = false ] && command -v php >/dev/null 2>&1; then
        if php artisan wayfinder:generate --with-form 2>/dev/null; then
            wayfinder_generated=true
        fi
    fi

    if [ "$wayfinder_generated" = false ]; then
        log_warn "Could not generate Wayfinder types. Checking if they already exist..."
        if [ ! -d "$PROJECT_DIR/resources/js/actions" ] || [ -z "$(ls -A "$PROJECT_DIR/resources/js/actions" 2>/dev/null)" ]; then
            log_warn "Wayfinder files missing! Build will likely fail."
            log_warn "Start Docker services first (./manage.sh start) or ensure PHP is available."
        fi
    fi

    # Build assets (skip Wayfinder plugin since we generated types above)
    log_info "Compiling TypeScript and React components..."
    SKIP_WAYFINDER=true pnpm run build
    
    log_info "Frontend assets built successfully!"
    log_info "Built files are in: public/build/"
}

# Create storage link
do_storage_link() {
    log_step "Creating storage link..."
    cd "$PROJECT_DIR"
    docker compose exec -T app php artisan storage:link 2>/dev/null || true
    log_info "Storage link created!"
}

# Backup database
# Creates two backup types:
#   1. Full backup (schema + data) - for exact restore to same schema
#   2. Data-only backup (with column inserts) - survives schema changes
do_cleanup() {
    log_step "Running backup cleanup..."
    
    # Parse command line options
    local retention_daily=${BACKUP_RETENTION_DAILY:-7}
    local retention_weekly=${BACKUP_RETENTION_WEEKLY:-4}
    local retention_monthly=${BACKUP_RETENTION_MONTHLY:-12}
    
    # Process options like --days=7 --weeks=4 --months=12
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --days=*)
                retention_daily="${1#*=}"
                shift
                ;;
            --weeks=*)
                retention_weekly="${1#*=}"
                shift
                ;;
            --months=*)
                retention_monthly="${1#*=}"
                shift
                ;;
            *)
                log_warn "Unknown option: $1"
                shift
                ;;
        esac
    done
    
    log_info "GFS Cleanup: daily=${retention_daily}d, weekly=${retention_weekly}w, monthly=${retention_monthly}m"
    
    # Get current timestamp for comparison
    local now=$(date +%s)
    
    # Process all backup files
    while IFS= read -r -d $'\0' file; do
        # Extract timestamp from filename: surprisemoi_{type}_{YYYYMMDD_HHMMSS}.sql.gz
        if [[ $file =~ surprisemoi_(full|data)_([0-9]{8})_([0-9]{6})\.sql\.gz ]]; then
            local file_date=${BASH_REMATCH[2]}
            local file_timestamp=$(date -d "${file_date:0:4}-${file_date:4:2}-${file_date:6:2}" +%s 2>/dev/null || continue)
            
            # Skip if date extraction failed
            if [ $? -ne 0 ]; then
                continue
            fi
            
            # Calculate age in days
            local age=$(( (now - file_timestamp) / 86400 ))
            
            # Determine backup type (daily, weekly, monthly)
            local day_of_week=$(date -d "${file_date:0:4}-${file_date:4:2}-${file_date:6:2}" +%u 2>/dev/null) # 1=Mon, 7=Sun
            local day_of_month=${file_date:6:2}
            
            # Check if this is a candidate for deletion
            local should_delete=0
            
            if [ $age -gt $((retention_monthly * 30)) ]; then
                # Older than monthly retention - delete
                should_delete=1
            elif [ $age -gt $((retention_weekly * 7)) ]; then
                # Check if this is a monthly backup (first day of month)
                if [ "$day_of_month" != "01" ]; then
                    should_delete=1
                fi
            elif [ $age -gt $retention_daily ]; then
                # Check if this is a weekly backup (Sunday)
                if [ "$day_of_week" != "7" ]; then
                    should_delete=1
                fi
            fi
            
            if [ $should_delete -eq 1 ]; then
                log_info "Deleting old backup: $(basename "$file")"
                rm -f "$file"
            fi
        fi
    done < <(find "$BACKUP_DIR" -name "surprisemoi_*.sql.gz" -type f -print0)
    
    log_info "Cleanup completed!"
}

do_backup() {
    log_step "Creating database backup..."
    
    mkdir -p "$BACKUP_DIR"
    local timestamp=$(date +"%Y%m%d_%H%M%S")
    local full_backup="$BACKUP_DIR/surprisemoi_full_$timestamp.sql"
    local data_backup="$BACKUP_DIR/surprisemoi_data_$timestamp.sql"
    
    cd "$PROJECT_DIR"
    
    # Get credentials from .env
    source "$ENV_FILE" 2>/dev/null || true
    local db_name="${DB_DATABASE:-surprise_moi_db}"
    local db_user="${DB_USERNAME:-laraveluser}"
    local db_password="${DB_PASSWORD:-Gilash@123}"
    local db_host="${DB_HOST:-db}"
    local db_port="${DB_PORT:-5432}"
    
    # Check if we can run pg_dump directly (inside container) or via docker compose (outside container)
    if command -v docker >/dev/null 2>&1 && [ -f "docker-compose.yml" ]; then
        # Running from outside container - use docker compose
        log_info "Creating full backup (schema + data)..."
        docker compose exec -T db pg_dump -U "$db_user" "$db_name" > "$full_backup"
        gzip "$full_backup"
        
        log_info "Creating portable data backup (survives schema changes)..."
        docker compose exec -T db pg_dump -U "$db_user" "$db_name" \
            --data-only \
            --column-inserts \
            --no-owner \
            --no-privileges \
            --exclude-table=migrations \
            --exclude-table=password_reset_tokens \
            --exclude-table=sessions \
            --exclude-table=failed_jobs \
            --exclude-table=job_batches \
            --exclude-table=jobs \
            --exclude-table=cache \
            --exclude-table=cache_locks \
            > "$data_backup"
        gzip "$data_backup"
    elif command -v pg_dump >/dev/null 2>&1; then
        # Running from inside container - connect directly to db
        log_info "Creating full backup (schema + data)..."
        PGPASSWORD="$db_password" pg_dump -h "$db_host" -p "$db_port" -U "$db_user" "$db_name" > "$full_backup"
        gzip "$full_backup"
        
        log_info "Creating portable data backup (survives schema changes)..."
        PGPASSWORD="$db_password" pg_dump -h "$db_host" -p "$db_port" -U "$db_user" "$db_name" \
            --data-only \
            --column-inserts \
            --no-owner \
            --no-privileges \
            --exclude-table=migrations \
            --exclude-table=password_reset_tokens \
            --exclude-table=sessions \
            --exclude-table=failed_jobs \
            --exclude-table=job_batches \
            --exclude-table=jobs \
            --exclude-table=cache \
            --exclude-table=cache_locks \
            > "$data_backup"
        gzip "$data_backup"
    else
        log_error "Could not find pg_dump command. Please run this script from outside the container or install PostgreSQL client tools."
        return 1
    fi
    
    # Run GFS cleanup after backup
    do_cleanup
    
    log_info "Backups saved:"
    log_info "  Full:     ${full_backup}.gz"
    log_info "  Portable: ${data_backup}.gz"
    echo ""
    log_info "Use 'restore' for full backup, 'restore-data' for portable backup"
}

# Restore database (full restore - same schema required)
do_restore() {
    local backup_file="$1"
    
    if [ -z "$backup_file" ]; then
        log_info "Available backups:"
        ls -lh "$BACKUP_DIR"/*.sql.gz 2>/dev/null || echo "No backups found"
        echo ""
        log_error "Usage: $0 restore <backup_file>"
        log_info "  For full restore (same schema):   $0 restore <full_backup.sql.gz>"
        log_info "  For data restore (any schema):    $0 restore-data <data_backup.sql.gz>"
        exit 1
    fi
    
    if [ ! -f "$backup_file" ]; then
        log_error "Backup file not found: $backup_file"
        exit 1
    fi
    
    log_warn "WARNING: This will COMPLETELY REPLACE the current database!"
    log_warn "The backup must match the current schema exactly."
    log_info "For schema-independent restore, use: $0 restore-data <file>"
    read -p "Continue with full restore? (yes/no): " confirm
    
    if [ "$confirm" != "yes" ]; then
        log_info "Restore cancelled."
        exit 0
    fi
    
    log_step "Restoring database (full)..."
    
    cd "$PROJECT_DIR"
    source "$ENV_FILE" 2>/dev/null || true
    local db_name="${DB_DATABASE:-surprise_moi_db}"
    local db_user="${DB_USERNAME:-laraveluser}"
    
    # Drop and recreate database for clean restore
    log_info "Dropping and recreating database..."
    docker compose exec -T db psql -U "$db_user" -d postgres -c "DROP DATABASE IF EXISTS $db_name;"
    docker compose exec -T db psql -U "$db_user" -d postgres -c "CREATE DATABASE $db_name OWNER $db_user;"
    
    log_info "Restoring from backup..."
    if [[ "$backup_file" == *.gz ]]; then
        gunzip -c "$backup_file" | docker compose exec -T db psql -U "$db_user" "$db_name"
    else
        docker compose exec -T db psql -U "$db_user" "$db_name" < "$backup_file"
    fi
    
    log_info "Full database restored!"
}

# Restore data only (survives schema changes)
# This runs migrations first, then imports data into the new schema
do_restore_data() {
    local backup_file="$1"
    
    if [ -z "$backup_file" ]; then
        log_info "Available data backups:"
        ls -lh "$BACKUP_DIR"/*_data_*.sql.gz 2>/dev/null || echo "No data backups found"
        echo ""
        log_error "Usage: $0 restore-data <data_backup.sql.gz>"
        exit 1
    fi
    
    if [ ! -f "$backup_file" ]; then
        log_error "Backup file not found: $backup_file"
        exit 1
    fi
    
    log_warn "WARNING: This will:"
    log_warn "  1. Fresh migrate (drop all tables, run all migrations)"
    log_warn "  2. Import data from backup (skipping errors for schema differences)"
    log_warn ""
    log_info "This is SAFE for schema changes - new columns get defaults, removed columns are ignored."
    read -p "Continue? (yes/no): " confirm
    
    if [ "$confirm" != "yes" ]; then
        log_info "Restore cancelled."
        exit 0
    fi
    
    log_step "Restoring data (schema-independent)..."
    
    cd "$PROJECT_DIR"
    source "$ENV_FILE" 2>/dev/null || true
    local db_name="${DB_DATABASE:-surprise_moi_db}"
    local db_user="${DB_USERNAME:-laraveluser}"
    
    # Step 1: Fresh migrate to get current schema
    log_info "Running fresh migrations to get current schema..."
    docker compose exec -T app php artisan migrate:fresh --force
    
    # Step 2: Disable foreign key checks and restore data
    log_info "Importing data (errors for missing/extra columns will be skipped)..."
    
    # Create a wrapper SQL that disables triggers during import
    local temp_restore="/tmp/restore_$$.sql"
    echo "SET session_replication_role = 'replica';" > "$temp_restore"
    
    if [[ "$backup_file" == *.gz ]]; then
        gunzip -c "$backup_file" >> "$temp_restore"
    else
        cat "$backup_file" >> "$temp_restore"
    fi
    
    echo "SET session_replication_role = 'origin';" >> "$temp_restore"
    
    # Run restore, continuing on errors (ON_ERROR_STOP=off)
    cat "$temp_restore" | docker compose exec -T db psql -U "$db_user" "$db_name" \
        -v ON_ERROR_STOP=off 2>&1 | grep -v "^INSERT" || true
    
    rm -f "$temp_restore"
    
    # Step 3: Reset sequences (auto-increment values)
    log_info "Resetting sequences..."
    docker compose exec -T db psql -U "$db_user" "$db_name" -c "
        DO \$\$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN (
                SELECT c.table_name, c.column_name
                FROM information_schema.columns c
                JOIN information_schema.tables t ON c.table_name = t.table_name
                WHERE c.column_default LIKE 'nextval%'
                AND t.table_schema = 'public'
            ) LOOP
                EXECUTE format('SELECT setval(pg_get_serial_sequence(%L, %L), COALESCE(MAX(%I), 1)) FROM %I',
                    r.table_name, r.column_name, r.column_name, r.table_name);
            END LOOP;
        END \$\$;
    "
    
    log_info "Data restored successfully!"
    log_info "Note: Some rows may have been skipped if columns were removed from schema."
}

# Setup SSL - Direct approach, sets up nginx and SSL in one go
do_ssl() {
    local email="${1:-admin@surprisemoi.com}"
    
    log_step "Setting up SSL certificate..."
    
    # Check if running as root
    if [ "$(id -u)" -ne 0 ]; then
        log_error "SSL setup requires root. Run: sudo $0 ssl $email"
        exit 1
    fi
    
    # Install certbot if needed
    if ! command -v certbot >/dev/null 2>&1; then
        log_info "Installing certbot..."
        apt-get update
        apt-get install -y certbot python3-certbot-nginx
    fi
    
    # Create certbot webroot directory
    mkdir -p /var/www/certbot
    
    # Setup HTTP nginx config first (required for certbot verification)
    log_info "Setting up nginx configuration..."
    
    # Remove any existing surprisemoi configs
    rm -f /etc/nginx/sites-enabled/surprisemoi* 2>/dev/null || true
    rm -f /etc/nginx/sites-available/surprisemoi* 2>/dev/null || true
    
    # Copy HTTP config for initial setup
    cp "$PROJECT_DIR/docker/nginx/surprisemoi.host.http.conf" /etc/nginx/sites-available/surprisemoi
    ln -sf /etc/nginx/sites-available/surprisemoi /etc/nginx/sites-enabled/surprisemoi
    
    # Test and reload nginx
    if nginx -t; then
        systemctl reload nginx
        log_info "Nginx HTTP config loaded successfully"
    else
        log_error "Nginx configuration test failed"
        exit 1
    fi
    
    # Get certificate - certbot will modify nginx config to add SSL
    log_info "Obtaining SSL certificate for $DOMAIN..."
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --email "$email" --redirect
    
    # Setup auto-renewal
    log_info "Setting up auto-renewal..."
    (crontab -l 2>/dev/null | grep -v "certbot renew"; echo "0 3 * * * certbot renew --quiet --post-hook 'systemctl reload nginx'") | crontab -
    
    log_info "SSL certificate installed and auto-renewal configured!"
    log_info "Your site should now be accessible at https://$DOMAIN"
}

# Setup host nginx (HTTP only, for when you want to skip SSL)
do_nginx() {
    log_step "Setting up host nginx configuration..."
    
    # Check if running as root
    if [ "$(id -u)" -ne 0 ]; then
        log_error "Nginx setup requires root. Run: sudo $0 nginx"
        exit 1
    fi
    
    # Check if nginx is installed
    if ! command -v nginx >/dev/null 2>&1; then
        log_error "Nginx is not installed. Install it first: apt-get install nginx"
        exit 1
    fi
    
    # Create certbot directory
    mkdir -p /var/www/certbot
    
    # Remove any existing config
    rm -f /etc/nginx/sites-enabled/surprisemoi 2>/dev/null || true
    rm -f /etc/nginx/sites-enabled/surprisemoi.host.conf 2>/dev/null || true
    rm -f /etc/nginx/sites-enabled/surprisemoi.host.http.conf 2>/dev/null || true
    
    # Check if SSL certificate exists
    if [ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
        log_info "SSL certificate found. Using HTTPS configuration..."
        cp "$PROJECT_DIR/docker/nginx/surprisemoi.host.conf" /etc/nginx/sites-available/surprisemoi
    else
        log_info "No SSL certificate found. Using HTTP-only configuration..."
        log_warn "Run 'sudo $0 ssl <email>' after this to enable HTTPS"
        cp "$PROJECT_DIR/docker/nginx/surprisemoi.host.http.conf" /etc/nginx/sites-available/surprisemoi
    fi
    
    # Enable the site
    ln -sf /etc/nginx/sites-available/surprisemoi /etc/nginx/sites-enabled/surprisemoi
    
    # Remove default site if exists (optional, prevents conflicts)
    # rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
    
    # Test configuration
    log_info "Testing nginx configuration..."
    if nginx -t; then
        systemctl reload nginx
        log_info "Nginx configured and reloaded successfully!"
        
        if [ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
            log_info "Site available at: https://$DOMAIN"
        else
            log_info "Site available at: http://$DOMAIN"
            log_warn "Remember to run 'sudo $0 ssl <email>' to enable HTTPS"
        fi
    else
        log_error "Nginx configuration test failed. Please check the config."
        exit 1
    fi
}

# View logs
do_logs() {
    local service="${1:-}"
    cd "$PROJECT_DIR"
    
    if [ -z "$service" ]; then
        docker compose logs -f --tail=100
    else
        docker compose logs -f --tail=100 "$service"
    fi
}

# Show status
do_status() {
    echo ""
    log_step "Service Status:"
    cd "$PROJECT_DIR"
    docker compose ps
    
    echo ""
    log_info "Application URL: http://localhost:8082"
    log_info "Domain: $DOMAIN"
    
    if services_running; then
        echo ""
        log_info "Quick health check:"
        curl -s -o /dev/null -w "  HTTP Status: %{http_code}\n" http://localhost:8082/up || echo "  Unable to reach application"

        echo ""
        log_step "Octane Status:"
        docker compose exec -T app php artisan octane:status 2>&1 || echo "  Unable to check Octane status"

        echo ""
        log_step "Horizon Status:"
        docker compose exec -T app php artisan horizon:status 2>&1 || echo "  Unable to check Horizon status"

        echo ""
        log_step "Supervisor Process Status:"
        docker compose exec -T app supervisorctl status 2>&1 || echo "  Unable to check Supervisor status"
    fi
}

# Run artisan command
do_artisan() {
    cd "$PROJECT_DIR"
    docker compose exec app php artisan "$@"
}

# =============================================================================
# INTELLIGENT DEPLOYMENT
# =============================================================================

do_deploy() {
    local fresh="${1:-}"
    
    print_banner
    
    # Check Docker
    log_step "Checking prerequisites..."
    if ! check_docker; then
        log_error "Docker is not installed or not running."
        exit 1
    fi
    log_info "Docker is ready!"
    
    # Setup if needed
    if ! env_configured; then
        log_action "Environment not configured. Running setup..."
        do_setup
    fi
    
    # Build
    log_action "Building application..."
    do_build "$fresh"
    
    # Build frontend assets
    log_action "Building frontend assets..."
    do_build_frontend
    
    # Start services
    log_action "Starting services..."
    do_start
    
    # Wait for DB
    log_action "Waiting for database to be ready..."
    local retries=0
    while [ $retries -lt 30 ]; do
        if docker compose exec -T db pg_isready -U laraveluser >/dev/null 2>&1; then
            break
        fi
        sleep 2
        retries=$((retries + 1))
    done
    
    # Run migrations
    log_action "Running migrations..."
    do_migrate
    
    # Optimize
    log_action "Optimizing application..."
    do_optimize
    
    # Storage link
    do_storage_link
    
    # Show status
    do_status
    
    echo ""
    log_info "🎉 Deployment completed successfully!"
    echo ""
    log_warn "Next steps to expose your site:"
    log_warn "  1. Setup nginx:  sudo $0 nginx"
    log_warn "  2. Setup SSL:    sudo $0 ssl developments@teczaleel.com"
    echo ""
    log_info "Or run both in one go:"
    log_info "  sudo $0 nginx && sudo $0 ssl developments@teczaleel.com"
}

# Quick update (for code changes)
do_update() {
    print_banner
    
    log_step "Quick update - pulling latest code and rebuilding..."
    
    cd "$PROJECT_DIR"
    
    # Pull latest
    if git rev-parse --git-dir > /dev/null 2>&1; then
        log_action "Pulling latest code..."
        git pull origin $(git branch --show-current) || true
    fi
    
    # Build frontend assets
    log_action "Building frontend assets..."
    do_build_frontend
    
    # Rebuild app only
    log_action "Rebuilding application..."
    docker compose build app
    
    # Restart
    log_action "Restarting services..."
    docker compose up -d app queue scheduler
    
    # Optimize
    sleep 5
    do_optimize
    
    # Migrate
    do_migrate
    
    do_status
    
    log_info "Update completed!"
}

# =============================================================================
# HELP
# =============================================================================

print_help() {
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Commands:"
    echo "  deploy [--fresh]     Full deployment (smart - detects what's needed)"
    echo "  update               Quick update (pull, rebuild, restart)"
    echo "  start                Start all services"
    echo "  stop                 Stop all services"
    echo "  restart              Restart all services"
    echo "  status               Show service status, health, Octane & Horizon"
    echo "  logs [service]       View logs (app, queue, scheduler, db, redis)"
    echo "  artisan <cmd>        Run artisan command"
    echo "  backup               Backup database (creates full + portable backups)"
    echo "  cleanup [--days=n] [--weeks=n] [--months=n]  Cleanup old backups with GFS policy"
    echo "  restore <file>       Full restore (requires same schema)"
    echo "  restore-data <file>  Portable restore (survives schema changes)"
    echo "  nginx                Setup host nginx (requires sudo)"
    echo "  ssl [email]          Setup SSL certificate (requires sudo)"
    echo "  setup                Run initial setup only"
    echo "  build [--fresh]      Build images only"
    echo "  build-frontend       Build frontend assets (TypeScript/React/Vite)"
    echo "  optimize             Run Laravel optimizations"
    echo "  help                 Show this help"
    echo ""
    echo "Backup/Restore Strategy:"
    echo "  backup creates TWO files:"
    echo "    *_full_*.sql.gz  - Exact copy (schema + data). Use with 'restore'."
    echo "    *_data_*.sql.gz  - Data only. Survives schema changes. Use with 'restore-data'."
    echo ""
    echo "Examples:"
    echo "  $0 deploy              # Smart full deployment"
    echo "  $0 deploy --fresh      # Fresh deployment (no cache)"
    echo "  $0 update              # Quick code update"
    echo "  $0 logs app            # View app logs"
    echo "  $0 artisan migrate     # Run migrations"
    echo "  $0 backup              # Backup database"
    echo "  $0 restore-data <file> # Restore data after schema changes"
    echo "  sudo $0 nginx          # Setup host nginx"
    echo "  sudo $0 ssl email      # Setup SSL certificate"
}

# =============================================================================
# MAIN
# =============================================================================

case "${1:-}" in
    deploy)
        do_deploy "${2:-}"
        ;;
    update)
        do_update
        ;;
    start)
        do_start
        do_status
        ;;
    stop)
        do_stop
        ;;
    restart)
        do_stop
        do_start
        do_status
        ;;
    status)
        do_status
        ;;
    logs)
        do_logs "${2:-}"
        ;;
    artisan)
        shift
        do_artisan "$@"
        ;;
    backup)
        do_backup
        ;;
    restore)
        do_restore "${2:-}"
        ;;
    restore-data)
        do_restore_data "${2:-}"
        ;;
    nginx)
        do_nginx
        ;;
    ssl)
        do_ssl "${2:-}"
        ;;
    setup)
        do_setup
        ;;
    build)
        do_build "${2:-}"
        ;;
    build-frontend)
        do_build_frontend
        ;;
    optimize)
        do_optimize
        ;;
    cleanup)
        shift
        do_cleanup "$@"
        ;;
    help|--help|-h)
        print_help
        ;;
    *)
        print_help
        exit 1
        ;;
esac

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

    # Pull latest code
    cd "$PROJECT_DIR"
    if git rev-parse --git-dir > /dev/null 2>&1; then
        log_action "Pulling latest code..."
        git pull origin $(git branch --show-current) || log_warn "Git pull failed - continuing with local code"
    fi

    # Build
    log_action "Building application..."
    do_build "$fresh"

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

    # Build frontend assets (after services are running so Wayfinder can use Docker)
    log_action "Building frontend assets..."
    do_build_frontend
    
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
    echo "  nginx                Setup host nginx (requires sudo)"
    echo "  ssl [email]          Setup SSL certificate (requires sudo)"
    echo "  setup                Run initial setup only"
    echo "  build [--fresh]      Build images only"
    echo "  build-frontend       Build frontend assets (TypeScript/React/Vite)"
    echo "  optimize             Run Laravel optimizations"
    echo "  help                 Show this help"
    echo ""
    echo "Examples:"
    echo "  $0 deploy              # Smart full deployment"
    echo "  $0 deploy --fresh      # Fresh deployment (no cache)"
    echo "  $0 update              # Quick code update"
    echo "  $0 logs app            # View app logs"
    echo "  $0 artisan migrate     # Run migrations"
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
    help|--help|-h)
        print_help
        ;;
    *)
        print_help
        exit 1
        ;;
esac

# =============================================================================
# SurpriseMoi Backend - Multi-stage Dockerfile
# This Dockerfile builds and runs the Laravel application with all assets
# Note: Wayfinder requires PHP during Vite build, so we use a combined approach
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Composer Builder - Install PHP dependencies first
# -----------------------------------------------------------------------------
FROM composer:2 AS composer-builder

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies without dev packages for production
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copy the rest of the application
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# -----------------------------------------------------------------------------
# Stage 2: Asset Builder - Build frontend with Node.js + PHP for Wayfinder
# -----------------------------------------------------------------------------
FROM node:20-alpine AS asset-builder

# Install PHP and required extensions for Wayfinder
RUN apk add --no-cache \
    php83 \
    php83-phar \
    php83-mbstring \
    php83-tokenizer \
    php83-fileinfo \
    php83-xml \
    php83-xmlwriter \
    php83-simplexml \
    php83-dom \
    php83-session \
    php83-iconv \
    php83-curl \
    php83-openssl \
    php83-pdo \
    php83-pdo_mysql \
    php83-pdo_pgsql \
    php83-pdo_sqlite \
    php83-sqlite3 \
    && ln -s /usr/bin/php83 /usr/bin/php

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install pnpm
RUN npm install -g pnpm

WORKDIR /app

# Copy composer files first
COPY composer.json composer.lock ./

# Install PHP dependencies (needed for Wayfinder)
RUN composer install --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# Copy Laravel application files needed for Wayfinder
COPY artisan ./
COPY bootstrap ./bootstrap
COPY config ./config
COPY routes ./routes
COPY app ./app

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Copy package files
COPY package.json pnpm-lock.yaml ./

# Install Node dependencies
RUN pnpm install --frozen-lockfile

# Copy frontend source files
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
COPY public ./public
COPY .env.example ./

# Create required storage directories for Laravel
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache

# Clear any cached bootstrap files from source
RUN rm -f bootstrap/cache/*.php

# Create .env with in-memory SQLite so Wayfinder can boot without a real DB.
# Production still uses PostgreSQL — this is only for route discovery during build.
RUN cp .env.example .env \
    && sed -i 's/DB_CONNECTION=pgsql/DB_CONNECTION=sqlite/' .env \
    && sed -i 's/DB_HOST=.*//' .env \
    && sed -i 's/DB_DATABASE=.*/DB_DATABASE=:memory:/' .env \
    && php artisan key:generate --ansi

# Generate Wayfinder routes BEFORE building (with form support for formVariants)
RUN php artisan wayfinder:generate --with-form

# Build the frontend assets (skip Wayfinder plugin since we already generated)
RUN SKIP_WAYFINDER=true pnpm run build

# -----------------------------------------------------------------------------
# Stage 3: Production Image
# -----------------------------------------------------------------------------
FROM php:8.3-cli-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    bash \
    postgresql-client \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    libpq-dev \
    icu-dev \
    linux-headers \
    openssl-dev \
    curl-dev \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache \
    sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Swoole extension
RUN pecl install swoole && docker-php-ext-enable swoole

# Clean up build dependencies
RUN apk del $PHPIZE_DEPS linux-headers openssl-dev curl-dev \
    && rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Create PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Create nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Create supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy application from composer builder
COPY --from=composer-builder /app .

# Copy built assets from asset builder (has both Node.js and PHP for Wayfinder)
COPY --from=asset-builder /app/public/build ./public/build

# Copy generated Wayfinder types
COPY --from=asset-builder /app/resources/js/actions ./resources/js/actions
COPY --from=asset-builder /app/resources/js/routes ./resources/js/routes

# Create required directories
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && mkdir -p storage/app/public

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x /var/www/html/scripts/manage.sh

# Create entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# =============================================================================
# Stage 4: Development - Extends production to avoid rebuilding PHP extensions
# =============================================================================
FROM production AS development

# Switch to development PHP config
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Add Composer for development
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Add Node.js and pnpm for frontend development
RUN apk add --no-cache nodejs npm \
    && npm install -g pnpm

WORKDIR /var/www/html

# Re-install composer dependencies (including dev packages)
COPY composer.json composer.lock ./
RUN composer install --no-scripts --prefer-dist

# Copy the rest of the application
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Build frontend assets (in-memory SQLite so Wayfinder can boot without real DB)
RUN cp .env.example .env \
    && sed -i 's/DB_CONNECTION=pgsql/DB_CONNECTION=sqlite/' .env \
    && sed -i 's/DB_HOST=.*//' .env \
    && sed -i 's/DB_DATABASE=.*/DB_DATABASE=:memory:/' .env \
    && php artisan key:generate --ansi \
    && pnpm install --frozen-lockfile \
    && php artisan wayfinder:generate --with-form \
    && SKIP_WAYFINDER=true pnpm run build \
    && rm .env

# Recreate directories and fix permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && mkdir -p storage/app/public

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x /var/www/html/scripts/manage.sh

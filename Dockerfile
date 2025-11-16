# Multi-stage Dockerfile for FleetManager Pro
# Development and Production targets

# Base stage with common dependencies
FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    zip \
    unzip \
    libpng-dev \
    libzip-dev \
    postgresql-dev \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

# Install Redis extension
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Development stage
FROM base AS development

# Install development dependencies
RUN apk add --no-cache \
    nodejs \
    npm

# Copy application files
COPY . .

# Install PHP dependencies (development)
RUN composer install --no-interaction --no-progress --optimize-autoloader

# Install Node dependencies
RUN npm install

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Expose port
EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# Production stage
FROM base AS production

# Copy application files
COPY . .

# Install PHP dependencies (production - no dev dependencies)
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --classmap-authoritative

# Build assets (if using Vite/Mix)
# RUN npm install && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Remove unnecessary files
RUN rm -rf \
    tests \
    .env.example \
    .git \
    .gitignore \
    .editorconfig \
    phpunit.xml \
    README.md

# Optimize Laravel
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Expose port
EXPOSE 9000

CMD ["php-fpm"]

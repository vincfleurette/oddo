# Fix 3: php/Dockerfile (UPDATED)
FROM php:8.4-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip \
    openssl-dev \
    libxml2-dev \
    curl-dev \
    bash \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    xml \
    curl \
    && rm -rf /var/cache/apk/* \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for better Docker layer caching)
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application source code
COPY . .

# Create storage directory with proper permissions
RUN mkdir -p /var/www/html/storage \
    && mkdir -p /tmp/oddo_cache \
    && chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /tmp/oddo_cache \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /tmp/oddo_cache

# Regenerate autoloader with the full application
RUN composer dump-autoload --optimize --no-dev

# Set proper permissions for the web directory
RUN chown -R www-data:www-data /var/www/html

# Configure PHP for production
RUN echo "display_errors = Off" >> /usr/local/etc/php/conf.d/production.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/production.ini \
    && echo "error_log = /var/log/php_errors.log" >> /usr/local/etc/php/conf.d/production.ini

ENTRYPOINT ["docker-php-entrypoint"]
CMD ["php-fpm"]
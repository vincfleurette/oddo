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

# Create storage directory and set permissions
RUN mkdir -p /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 755 /var/www/html/storage

# Regenerate autoloader with the full application
RUN composer dump-autoload --optimize --no-dev

# Set proper permissions for the web directory
RUN chown -R www-data:www-data /var/www/html

ENTRYPOINT ["docker-php-entrypoint"]
CMD ["php-fpm"]
FROM php:8.4-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache       $PHPIZE_DEPS       oniguruma-dev       libzip-dev       zip       unzip       openssl-dev       libxml2-dev       curl-dev       bash     && docker-php-ext-install       pdo_mysql       mbstring       zip       xml       curl     && rm -rf /var/cache/apk/*     && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
# après COPY . .
RUN mkdir -p /var/www/html/storage \
 && chown -R www-data:www-data /var/www/html/storage
 
 
# Copy only composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application code
COPY . .

ENTRYPOINT ["docker-php-entrypoint"]
CMD ["php-fpm"]

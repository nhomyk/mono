### Build stage: install composer deps and build artifacts
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress || true

### Production image
FROM php:8.1-fpm-alpine
RUN apk add --no-cache sqlite libzip zlib-dev libpng libpng-dev libxml2-dev
RUN docker-php-ext-install pdo pdo_sqlite

WORKDIR /var/www/html

# Copy vendor from builder
COPY --from=vendor /app/vendor /var/www/html/vendor

# Copy app
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000
CMD ["php-fpm"]

# Lean dev image that runs `php artisan serve`
FROM php:8.3-cli-alpine

# System deps (runtime + dev libs for extensions you compile)
RUN apk add --no-cache \
    git bash icu-dev oniguruma-dev libzip-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev

# Core PHP extensions
RUN docker-php-ext-configure intl \
 && docker-php-ext-install -j"$(nproc)" intl pcntl bcmath opcache pdo_mysql mbstring zip

# GD (needs the -dev libs you installed above)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd

# phpredis (install build deps temporarily)
RUN set -eux; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apk del .build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Non-root dev user
RUN adduser -D -u 1000 appuser && chown -R appuser:appuser /var/www/html
USER appuser

EXPOSE 8000
CMD php artisan serve --host=0.0.0.0 --port=8000

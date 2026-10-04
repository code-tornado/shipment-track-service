# ---- frontend assets ---------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
RUN npm run build

# ---- php dependencies --------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --ignore-platform-reqs

# ---- runtime -----------------------------------------------------------------
FROM php:8.3-cli-alpine

RUN apk add --no-cache libpq libzip icu-libs libpng libjpeg-turbo freetype \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_mysql zip gd intl bcmath pcntl opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-scripts \
    && php artisan package:discover --ansi \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

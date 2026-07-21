# ZimboSocials — PHP-FPM image for the Laravel app.
#
# Runs the same PHP 8.3 as the cPanel host it migrates from. Front-end assets
# are built in a separate stage so node isn't shipped in the runtime image.

# ── Stage 1: build front-end assets ───────────────────────────────────────────
FROM node:20-alpine AS assets
WORKDIR /build
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tsconfig.json ./
COPY public ./public
RUN npm run build

# ── Stage 2: PHP runtime ──────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine

# intl/gd/zip cover image handling (payment proofs) and exports; pdo_mysql is
# the production driver; pcntl lets queue workers handle signals for graceful
# restarts.
RUN apk add --no-cache \
        git curl bash mysql-client \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath gd zip intl exif pcntl opcache \
    && apk del icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP deps first so code changes don't bust the dependency layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /build/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/app/public storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# php-fpm listens here; the nginx service proxies to it.
EXPOSE 9000
CMD ["php-fpm"]

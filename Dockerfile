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
FROM php:8.3-fpm-alpine AS app

# intl/gd/zip cover image handling (payment proofs) and exports; pdo_mysql is
# the production driver; pcntl lets queue workers handle signals for graceful
# restarts.
# The -dev packages are build headers only, and deleting them also takes the
# runtime .so files with them (nothing else depends on them), which leaves the
# compiled extensions unable to load: "libpng16.so.16: No such file". So the
# runtime libraries are installed separately and kept, and only the build group
# is removed.
RUN apk add --no-cache \
        git curl bash mysql-client \
        icu-libs libzip libpng libjpeg-turbo freetype oniguruma \
    && apk add --no-cache --virtual .build-deps \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath gd zip intl exif pcntl opcache \
    && apk del --no-network .build-deps \
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

# ── Stage 3: web server ───────────────────────────────────────────────────────
#
# nginx needs the SAME public/ directory php-fpm is serving from: it resolves
# try_files against real paths and serves the built assets itself. Running a
# bare nginx image here means /var/www/html/public is empty, so every request —
# including index.php — 404s before php-fpm is ever reached.
#
# The files are copied from the app stage rather than shared through a named
# volume, because Docker only seeds a volume when it is empty: a volume would
# quietly keep serving the previous release's assets after every rebuild.
FROM nginx:alpine AS web
COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

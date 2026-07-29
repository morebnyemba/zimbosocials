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
# postcss.config.js is what actually runs Tailwind, and tailwind.config.js holds
# the content globs. Without both, the @tailwind directives compile to nothing
# and app.css ships as a couple hundred bytes of hand-written rules — the site
# renders completely unstyled against a perfectly healthy server.
COPY vite.config.js tsconfig.json postcss.config.js tailwind.config.js ./
COPY public ./public
RUN npm run build \
    # Fail the build loudly rather than shipping an empty stylesheet: a real
    # Tailwind bundle is tens of kilobytes, so anything tiny means the config
    # did not take effect.
    && css=$(find public/build/assets -name '*.css' -print0 | xargs -0 cat | wc -c) \
    && if [ "$css" -lt 10000 ]; then \
        echo "✗ Built CSS is only ${css} bytes — Tailwind produced nothing."; exit 1; \
    fi

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

# The stock pool allows five workers. The WhatsApp webhook calls Gemini inline,
# so each inbound message holds a worker for seconds at a time — two or three
# concurrent conversations consume the pool, and then every other request queues
# behind them until nginx gives up and returns 504 (which is what a login
# timing out during a busy period actually is).
RUN { \
        echo '[www]'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 24'; \
        echo 'pm.start_servers = 6'; \
        echo 'pm.min_spare_servers = 4'; \
        echo 'pm.max_spare_servers = 12'; \
        echo 'pm.max_requests = 500'; \
    } > /usr/local/etc/php-fpm.d/zz-pool.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP deps first so code changes don't bust the dependency layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /build/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/app/public storage/logs bootstrap/cache \
    # public/storage must exist in the IMAGE, not just be created at runtime by
    # `artisan storage:link`: the web stage copies public/ from here, so a
    # symlink made later inside the app container never reaches nginx — and
    # every payment proof and archived WhatsApp photo or voice note 404s while
    # being perfectly present on disk.
    && ln -sfn ../storage/app/public public/storage \
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

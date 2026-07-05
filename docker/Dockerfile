# syntax=docker/dockerfile:1

###############################################################################
# base — shared PHP-FPM + Nginx + Supervisor runtime
###############################################################################
FROM php:8.4-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    curl \
    && rm -rf /var/lib/apt/lists/*

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions gd intl pdo_mysql opcache

WORKDIR /var/www/html

EXPOSE 8000

###############################################################################
# dev — development image: source is bind-mounted, configs are symlinked from
# the mount so edits under docker/ apply with a reload instead of a rebuild
###############################################################################
FROM base AS dev

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions xdebug @composer

RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

RUN ln -sf /var/www/html/docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf \
    && ln -sf /var/www/html/docker/nginx.conf /etc/nginx/sites-available/default \
    && ln -sf /var/www/html/docker/php.ini /usr/local/etc/php/conf.d/app.ini

COPY --chmod=0755 docker/entrypoint.dev.sh /usr/local/bin/docker-entrypoint-dev.sh

ENTRYPOINT ["docker-entrypoint-dev.sh"]

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

###############################################################################
# assets-build — frontend SCSS + Angular admin SPA production build
###############################################################################
FROM node:22-alpine AS assets-build

WORKDIR /build

COPY package.json package-lock.json ./
COPY admin-app/package.json admin-app/package-lock.json admin-app/
RUN npm ci --ignore-scripts && npm ci --prefix admin-app

COPY assets/ assets/
COPY admin-app/ admin-app/
RUN npm run build

###############################################################################
# composer-deps — production PHP dependencies (no dev packages, no scripts)
###############################################################################
FROM php:8.4-cli AS composer-deps

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions intl @composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --no-interaction

###############################################################################
# prod — immutable production image: code, vendors and built assets baked in
###############################################################################
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/nginx.prod.conf /etc/nginx/sites-available/default
COPY docker/php.prod.ini /usr/local/etc/php/conf.d/app.ini
COPY --chmod=0755 docker/entrypoint.prod.sh /usr/local/bin/docker-entrypoint-prod.sh

COPY . .
COPY --from=composer-deps /app/vendor vendor/
COPY --from=assets-build /build/public/admin public/admin/
COPY --from=assets-build /build/assets/styles/frontend.css assets/styles/frontend.css

COPY --from=composer-deps /usr/local/bin/composer /usr/local/bin/composer
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && rm /usr/local/bin/composer

RUN php bin/console assets:install public --no-interaction \
    && php bin/console importmap:install --no-interaction \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup \
    && mkdir -p public/uploads \
    && chown -R www-data:www-data var public/uploads

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s \
    CMD curl -fsS http://127.0.0.1:8000/healthz || exit 1

ENTRYPOINT ["docker-entrypoint-prod.sh"]

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

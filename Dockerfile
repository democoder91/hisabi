# syntax=docker/dockerfile:1

FROM php:8.2-fpm-alpine AS php-base

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    curl \
    freetype \
    libjpeg-turbo \
    libpng \
    libwebp \
    libzip \
    unzip \
    zip \
    && apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath gd mysqli pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

FROM php-base AS vendor

COPY . /var/www/html

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

FROM node:20-alpine AS assets

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci

COPY . /var/www/html
COPY --from=vendor /var/www/html/vendor /var/www/html/vendor

RUN npm run build

FROM php-base AS app

ENV APP_ENV=production \
    APP_DEBUG=false

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /var/www/html/public/build /var/www/html/public/build

RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && rm -rf public/storage \
    && ln -s /var/www/html/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]

FROM nginx:1.27-alpine AS nginx

WORKDIR /var/www/html

COPY docker/nginx/conf.d/app.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
COPY --from=app /var/www/html/storage/app/public /var/www/html/storage/app/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]

# syntax=docker/dockerfile:1

FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.js postcss.config.js tailwind.config.js ./
RUN npm run build

FROM composer:2 AS composer_deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --optimize

FROM php:8.2-fpm
WORKDIR /var/www/html
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev nginx gettext-base \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer_deps /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY docker/nginx.conf.template /etc/nginx/conf.d/default.conf.template
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
EXPOSE 80
CMD ["sh", "-c", "export PORT=${PORT:-8080}; envsubst '$PORT' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf; php-fpm -D; nginx -g 'daemon off;'"]

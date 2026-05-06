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

FROM php:8.2-apache
WORKDIR /var/www/html
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql \
    && a2dismod mpm_event mpm_worker mpm_prefork \
    && rm -f /etc/apache2/mods-enabled/mpm_event.* \
       /etc/apache2/mods-enabled/mpm_worker.* \
       /etc/apache2/mods-enabled/mpm_prefork.* \
    && a2enmod mpm_prefork rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer_deps /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build
RUN chown -R www-data:www-data storage bootstrap/cache \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf
EXPOSE 80

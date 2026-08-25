# ==========================================
# Stage 1: Build Frontend (Vue / Node.js 24)
# ==========================================
FROM node:24-alpine AS frontend-builder
WORKDIR /app
RUN corepack enable

WORKDIR /app/frontend
COPY frontend/ ./
RUN yarn install --immutable && yarn build


# ==========================================
# Stage 2: Build Backend (PHP 8.2 / Composer)
# ==========================================
FROM php:8.2-cli AS backend-builder
WORKDIR /app

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev \
    && docker-php-ext-install zip bcmath 

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY backend/composer.json backend/composer.lock ./backend/
WORKDIR /app/backend
RUN composer install --no-dev --optimize-autoloader
COPY backend/ ./


# ==========================================
# Stage 3: Production Application Image (PHP-FPM)
# ==========================================
FROM php:8.2-fpm AS production
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mysqli zip bcmath 

# Copy compiled frontend assets
COPY --chown=www-data:www-data --from=frontend-builder /app/frontend/dist /var/www/html

# Create nested api directory and copy compiled backend assets
COPY --chown=www-data:www-data --from=backend-builder /app/backend /var/www/html/api
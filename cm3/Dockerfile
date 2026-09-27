# ==========================================
# Stage 1: Build Frontend (Vue / Node.js 24)
# ==========================================
FROM node:24-alpine AS frontend-builder
WORKDIR /app/frontend
COPY frontend/ ./
RUN corepack enable && yarn install --immutable && yarn build

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
# Stage 3: Production PHP-FPM (Backend)
# ==========================================
FROM php:8.2-fpm AS backend
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mysqli zip bcmath gd
COPY --chown=www-data:www-data --from=backend-builder /app/backend /var/www/html

# ==========================================
# Stage 4: Production Nginx (Frontend + Orchestrator)
# ==========================================
FROM nginx:alpine AS frontend
COPY --from=frontend-builder /app/frontend/dist /usr/share/nginx/html
COPY --chmod=755  nginx-entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

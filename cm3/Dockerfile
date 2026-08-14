# ==========================================
# Stage 1: Build Frontend (Vue / Node.js 24)
# ==========================================
FROM node:24-alpine AS frontend-builder
WORKDIR /app
RUN corepack enable

# Copy frontend config files
COPY cm3/frontend/package.json cm3/frontend/yarn.lock ./cm3/frontend/
COPY cm3/frontend/ ./cm3/frontend/

WORKDIR /app/cm3/frontend
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

# Copy backend config files safely preserving the cm3/ structure
COPY cm3/backend/composer.json cm3/backend/composer.lock ./cm3/backend/
COPY cm3/backend/ ./cm3/backend/

WORKDIR /app/cm3/backend
RUN composer install --no-dev --optimize-autoloader


# ==========================================
# Stage 3: Production Application Image (PHP-FPM)
# ==========================================
FROM php:8.2-fpm AS production
WORKDIR /var/www/html

RUN docker-php-ext-install pdo_mysql mysqli

# Copy compiled frontend assets
COPY --chown=www-data:www-data --from=frontend-builder /app/cm3/frontend/dist /var/www/html

# Create nested api directory and copy compiled backend assets
COPY --chown=www-data:www-data --from=backend-builder /app/cm3/backend /var/www/html/api


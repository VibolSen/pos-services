# ==============================================================================
# CodeBridges Enterprise - Unified Cloud Container (Render Production)
# Packages: Nginx Gateway + 6 Laravel Microservices + Supervisor
# ==============================================================================
FROM php:8.2-cli-alpine

# 1. Install System Tools, Nginx, Supervisor, PHP Extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    curl \
    git \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    mysql-client \
    linux-headers \
    && docker-php-ext-install pdo_mysql bcmath zip sockets

# 2. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Create app directories
WORKDIR /var/www

# 4. Copy all 6 Laravel microservices
COPY ./auth-service /var/www/auth-service
COPY ./catalog-service /var/www/catalog-service
COPY ./inventory-service /var/www/inventory-service
COPY ./sales-service /var/www/sales-service
COPY ./payment-service /var/www/payment-service
COPY ./shift-service /var/www/shift-service

# 5. Install Composer dependencies for each service
RUN for dir in auth-service catalog-service inventory-service sales-service payment-service shift-service; do \
      if [ -f "/var/www/$dir/composer.json" ]; then \
        echo "Installing dependencies for $dir..." && \
        cd /var/www/$dir && \
        composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction && \
        mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && \
        chmod -R 777 storage bootstrap/cache || true; \
      fi \
    done

# 6. Configure Nginx and Supervisor
COPY ./docker/cloud-nginx.conf /etc/nginx/nginx.conf
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./docker/start-unified.sh /usr/local/bin/start-unified.sh
RUN chmod +x /usr/local/bin/start-unified.sh

# 7. Expose Gateway Port
EXPOSE 80

# 8. Start All Services & Nginx Gateway via Supervisor
CMD ["/usr/local/bin/start-unified.sh"]

#!/bin/bash
set -e

echo "=========================================================="
echo "🚀 Initializing CodeBridges Enterprise Cloud Services"
echo "=========================================================="

# 0. Ensure system run and log directories exist for Nginx and Supervisor
mkdir -p /run/nginx /var/log/nginx /var/log/supervisor /var/run

PORT_TO_LISTEN="${PORT:-80}"
echo "🌐 Configuring Nginx to bind on port ${PORT_TO_LISTEN}..."
sed -i "s/listen 80;/listen ${PORT_TO_LISTEN};/g" /etc/nginx/nginx.conf

SERVICES=("auth-service" "catalog-service" "inventory-service" "sales-service" "payment-service" "shift-service")
DB_NAMES=("auth_db" "catalog_db" "inventory_db" "sales_db" "payment_db" "shift_db")

# 1. Fast Setup of Environment, Keys & Permissions for all 6 microservices
for i in "${!SERVICES[@]}"; do
    svc="${SERVICES[$i]}"
    db_name="${DB_NAMES[$i]}"
    
    if [ -d "/var/www/$svc" ]; then
        cd "/var/www/$svc"
        
        # Setup storage and cache directories
        mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
        chmod -R 777 storage bootstrap/cache 2>/dev/null || true
        
        # Ensure .env exists
        if [ ! -f ".env" ]; then
            if [ -f ".env.example" ]; then
                cp .env.example .env
            else
                touch .env
            fi
        fi

        # Inject Database configuration
        if [ -n "$DB_HOST" ]; then
            sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/g" .env 2>/dev/null || echo "DB_CONNECTION=mysql" >> .env
            sed -i "s/^DB_HOST=.*/DB_HOST=$DB_HOST/g" .env 2>/dev/null || echo "DB_HOST=$DB_HOST" >> .env
            sed -i "s/^DB_PORT=.*/DB_PORT=${DB_PORT:-3306}/g" .env 2>/dev/null || echo "DB_PORT=${DB_PORT:-3306}" >> .env
            sed -i "s/^DB_DATABASE=.*/DB_DATABASE=$db_name/g" .env 2>/dev/null || echo "DB_DATABASE=$db_name" >> .env
            if [ -n "$DB_USERNAME" ]; then
                sed -i "s/^DB_USERNAME=.*/DB_USERNAME=$DB_USERNAME/g" .env 2>/dev/null || echo "DB_USERNAME=$DB_USERNAME" >> .env
            fi
            if [ -n "$DB_PASSWORD" ]; then
                sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/g" .env 2>/dev/null || echo "DB_PASSWORD=$DB_PASSWORD" >> .env
            fi
        fi

        # Enforce file-based sessions and cache (prevents DB session lookups on Swagger docs / health checks)
        sed -i "s/^SESSION_DRIVER=.*/SESSION_DRIVER=file/g" .env 2>/dev/null || echo "SESSION_DRIVER=file" >> .env
        sed -i "s/^CACHE_STORE=.*/CACHE_STORE=file/g" .env 2>/dev/null || echo "CACHE_STORE=file" >> .env
        sed -i "s/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/g" .env 2>/dev/null || echo "QUEUE_CONNECTION=sync" >> .env

        # Generate APP_KEY if missing
        if [ -f "artisan" ]; then
            php artisan key:generate --force 2>/dev/null || true
            php artisan config:clear 2>/dev/null || true
            php artisan route:clear 2>/dev/null || true
        fi
    fi
done

# 2. Asynchronous Database Provisioning & Migrations (Runs in background so Web Server boots instantly)
(
    sleep 2
    if [ -n "$DB_HOST" ] && [ -n "$DB_USERNAME" ] && [ -n "$DB_PASSWORD" ]; then
        echo "📦 [Async DB] Ensuring cloud databases exist on Aiven MySQL..."
        mysql --connect-timeout=5 -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "
            CREATE DATABASE IF NOT EXISTS auth_db;
            CREATE DATABASE IF NOT EXISTS catalog_db;
            CREATE DATABASE IF NOT EXISTS inventory_db;
            CREATE DATABASE IF NOT EXISTS sales_db;
            CREATE DATABASE IF NOT EXISTS payment_db;
            CREATE DATABASE IF NOT EXISTS shift_db;
        " 2>/dev/null || true

        for i in "${!SERVICES[@]}"; do
            svc="${SERVICES[$i]}"
            db_name="${DB_NAMES[$i]}"
            if [ -d "/var/www/$svc" ]; then
                cd "/var/www/$svc"
                echo "🚀 [Async DB] Running migrations for $svc ($db_name)..."
                php artisan migrate --force 2>/dev/null || true
                php artisan db:seed --force 2>/dev/null || true
            fi
        done
        echo "✅ [Async DB] All database migrations completed."
    fi
) &

echo "Verifying Nginx configuration syntax..."
nginx -t || true

echo "Starting Supervisor (Nginx on port ${PORT_TO_LISTEN} + 6 Microservices)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

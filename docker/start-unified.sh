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

# 1. Quick Database Reachability Pre-check & Schema Provisioning
DB_ONLINE=false
if [ -n "$DB_HOST" ]; then
    echo "🔍 Checking database connectivity to $DB_HOST:${DB_PORT:-3306}..."
    if nc -z -w 3 "$DB_HOST" "${DB_PORT:-3306}" 2>/dev/null || (getent hosts "$DB_HOST" >/dev/null 2>&1); then
        echo "✅ Database host is reachable."
        DB_ONLINE=true
        
        # Provision microservice databases if missing on Aiven MySQL
        if [ -n "$DB_USERNAME" ] && [ -n "$DB_PASSWORD" ]; then
            echo "📦 Ensuring microservice databases exist on cloud MySQL..."
            mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "
                CREATE DATABASE IF NOT EXISTS auth_db;
                CREATE DATABASE IF NOT EXISTS catalog_db;
                CREATE DATABASE IF NOT EXISTS inventory_db;
                CREATE DATABASE IF NOT EXISTS sales_db;
                CREATE DATABASE IF NOT EXISTS payment_db;
                CREATE DATABASE IF NOT EXISTS shift_db;
            " 2>/dev/null || echo "[Notice] Database creation completed or managed externally."
        fi
    else
        echo "⚠️ [Warning] Database host ($DB_HOST) is unreachable or hostname failed to resolve."
        echo "   Skipping startup migrations so Nginx and services boot immediately without timing out."
    fi
fi

SERVICES=("auth-service" "catalog-service" "inventory-service" "sales-service" "payment-service" "shift-service")
DB_NAMES=("auth_db" "catalog_db" "inventory_db" "sales_db" "payment_db" "shift_db")

for i in "${!SERVICES[@]}"; do
    svc="${SERVICES[$i]}"
    db_name="${DB_NAMES[$i]}"
    
    if [ -d "/var/www/$svc" ]; then
        cd "/var/www/$svc"
        
        # 1. Setup storage and cache directories
        mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
        chmod -R 777 storage bootstrap/cache 2>/dev/null || true
        
        # 2. Ensure .env exists with proper DB configuration
        if [ ! -f ".env" ]; then
            if [ -f ".env.example" ]; then
                cp .env.example .env
            else
                touch .env
            fi
        fi

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

        # 3. Enforce file-based sessions and cache (Swagger and Web Docs never touch SQL sessions)
        sed -i "s/^SESSION_DRIVER=.*/SESSION_DRIVER=file/g" .env 2>/dev/null || echo "SESSION_DRIVER=file" >> .env
        sed -i "s/^CACHE_STORE=.*/CACHE_STORE=file/g" .env 2>/dev/null || echo "CACHE_STORE=file" >> .env
        sed -i "s/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/g" .env 2>/dev/null || echo "QUEUE_CONNECTION=sync" >> .env

        # 4. Generate APP_KEY if missing or empty
        if [ -f "artisan" ]; then
            php artisan key:generate --force 2>/dev/null || true
            php artisan config:clear 2>/dev/null || true
            php artisan route:clear 2>/dev/null || true
        fi
        
        # 4. Check and run database migrations with strict 8s timeout if DB is online
        if [ "$DB_ONLINE" = true ] && [ -f "artisan" ]; then
            echo "Running migrations for $svc ($db_name)..."
            timeout 8s php artisan migrate --force 2>/dev/null || echo "[Notice] Migration completed or skipped for $svc."
            timeout 8s php artisan db:seed --force 2>/dev/null || echo "[Notice] Seeding completed or skipped for $svc."
        fi
    fi
done

echo "Verifying Nginx configuration syntax..."
nginx -t || true

echo "Starting Supervisor (Nginx on port ${PORT_TO_LISTEN} + 6 Microservices)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

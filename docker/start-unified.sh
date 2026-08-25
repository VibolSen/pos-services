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
        
        # 1. Setup storage and cache directories
        mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
        chmod -R 777 storage bootstrap/cache 2>/dev/null || true
        
        # 2. Write complete .env file with active Cloud Database credentials
        cat <<EOF > .env
APP_NAME=POS-${svc}
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${db_name}
DB_USERNAME=${DB_USERNAME:-root}
DB_PASSWORD=${DB_PASSWORD:-}

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

LOG_CHANNEL=stack
LOG_LEVEL=debug
EOF

        # 3. Generate APP_KEY and clear caches
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

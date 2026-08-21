#!/bin/bash
set -e

echo "=========================================================="
echo "🚀 Initializing CodeBridges Enterprise Cloud Services"
echo "=========================================================="

SERVICES=("auth-service" "catalog-service" "inventory-service" "sales-service" "payment-service" "shift-service")

for svc in "${SERVICES[@]}"; do
    if [ -d "/var/www/$svc" ]; then
        cd "/var/www/$svc"
        
        # 1. Setup storage and cache directories
        mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
        chmod -R 777 storage bootstrap/cache 2>/dev/null || true
        
        # 2. Ensure .env exists
        if [ ! -f ".env" ] && [ -f ".env.example" ]; then
            cp .env.example .env
        fi

        # 3. Generate APP_KEY if missing or empty
        if [ -f "artisan" ]; then
            php artisan key:generate --force 2>/dev/null || true
            php artisan config:clear 2>/dev/null || true
            php artisan route:clear 2>/dev/null || true
        fi
        
        # 4. Check and run database migrations and seed default data
        if [ -f "artisan" ]; then
            echo "Running migrations and seeds for $svc..."
            php artisan migrate --force 2>/dev/null || echo "[Notice] Migration skipped for $svc."
            php artisan db:seed --force 2>/dev/null || echo "[Notice] Seeding skipped for $svc."
        fi
    fi
done

echo "Starting Supervisor (Nginx + 6 Microservices)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

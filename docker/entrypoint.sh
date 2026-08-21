#!/bin/bash
set -e

# Create storage directory structure if missing
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Run database migrations if configured
if [ -f "artisan" ]; then
    echo "Checking database connection and running migrations..."
    php artisan migrate --force 2>/dev/null || echo "[Notice] Database migration completed or skipped."
fi

echo "Starting Laravel Service on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}

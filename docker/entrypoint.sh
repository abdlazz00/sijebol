#!/bin/bash
set -e

# Pastikan folder storage dan cache selalu ada dengan permission yang benar
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/private \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Jalankan database migrations jika diaktifkan via environment variable
if [ "$AUTO_MIGRATE" = "true" ]; then
    echo "Running database migrations..."
    php /var/www/html/artisan migrate --force
fi

# Caching Laravel untuk performa produksi / staging
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "Optimizing Laravel configuration, routes, and views..."
    php /var/www/html/artisan config:cache || true
    php /var/www/html/artisan route:cache || true
    php /var/www/html/artisan view:cache || true
fi

exec "$@"

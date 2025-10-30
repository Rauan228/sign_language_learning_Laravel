#!/usr/bin/env bash
set -e

echo "Run migrations..."
php /var/www/html/artisan migrate --force || true

# Стартует php-fpm и nginx
exec /start.sh

#!/usr/bin/env bash
set -e

echo "Running migrations..."
php artisan migrate --force || true

# Запускаем nginx + php-fpm
exec /entrypoint supervisord

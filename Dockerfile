# Nginx + PHP-FPM + Composer в одном контейнере
FROM webdevops/php-nginx:8.3

# Laravel использует public как webroot
ENV WEB_DOCUMENT_ROOT=/app/public

# Установим нужное расширение MySQL
RUN install-php-extensions pdo_mysql

# Копируем код в контейнер
WORKDIR /app
COPY . /app

# Устанавливаем зависимости PHP и кэшируем конфиг
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
 && php artisan config:cache \
 && php artisan route:cache

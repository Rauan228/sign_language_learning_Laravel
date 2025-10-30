# Nginx + PHP-FPM + Composer в одном контейнере
FROM webdevops/php-nginx:8.3

# Laravel использует public как webroot
ENV WEB_DOCUMENT_ROOT=/app/public

# Установим расширение PDO MySQL через пакетный менеджер (в образе нет install-php-extensions)
RUN apt-get update \
 && (apt-get install -y --no-install-recommends php8.3-mysql \
     || apt-get install -y --no-install-recommends php-mysql) \
 && rm -rf /var/lib/apt/lists/*

# Копируем код в контейнер
WORKDIR /app
COPY . /app

# Устанавливаем зависимости PHP и кэшируем конфиг
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
 && php artisan config:cache \
 && php artisan route:cache

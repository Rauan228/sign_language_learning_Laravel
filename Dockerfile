# Базовый образ с Nginx+PHP-FPM
FROM richarvey/nginx-php-fpm:2.2.0

# Laravel будет обслуживаться из public
ENV WEBROOT=/var/www/html/public

# Скопировать код
COPY . /var/www/html

# Установить зависимости и подготовить кэш (Composer уже есть в образе)
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --working-dir=/var/www/html \
 && php /var/www/html/artisan config:cache \
 && php /var/www/html/artisan route:cache

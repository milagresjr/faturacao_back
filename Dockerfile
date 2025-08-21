FROM php:8.2-fpm

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    curl git unzip libpq-dev libzip-dev zip libonig-dev \
    && docker-php-ext-install pdo pdo_mysql

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www

COPY . .

RUN composer install

# Rodar migrations automaticamente quando iniciar (opcional)
# CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000

CMD php artisan serve --host=0.0.0.0 --port=8000


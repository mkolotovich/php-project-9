FROM php:8.2-cli


RUN apt-get update && apt-get install -y libzip-dev libpq-dev postgresql-client
RUN docker-php-ext-install zip pdo pdo_pgsql

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

WORKDIR /app

COPY . .
COPY run_migrations.sh /

RUN chmod +x /run_migrations.sh
RUN composer install

CMD ["/run_migrations.sh", "bash", "-c", "make start"]
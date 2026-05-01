FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git curl unzip nginx supervisor \
    icu-dev libzip-dev oniguruma-dev

RUN docker-php-ext-install \
    pdo_mysql intl zip opcache mbstring

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN mkdir -p var/cache var/log && \
    chown -R www-data:www-data var/ && \
    php bin/console cache:warmup --env=prod

COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

CMD ["/usr/local/bin/entrypoint.sh"]

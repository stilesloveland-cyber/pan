FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    gd-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    libzip-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip \
    && apk del $PHPIZE_DEPS linux-headers

RUN mkdir -p /var/www/html/pan /var/www/uploads /run/nginx /var/log/supervisor

COPY . /var/www/html/pan/
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/pan.ini
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/supervisord.conf /etc/supervisord.conf

RUN chmod +x /entrypoint.sh

EXPOSE 80

VOLUME ["/var/www/uploads"]

CMD ["/entrypoint.sh"]

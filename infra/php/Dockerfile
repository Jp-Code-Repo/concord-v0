# One image for every Concord Laravel service. Services differ by mounted code + env.
FROM php:8.3-fpm-alpine

ARG UID=1000
ARG GID=1000

COPY --from=mlocati/docker-php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache git unzip curl shadow \
 && install-php-extensions pdo_mysql redis opcache pcntl bcmath \
 && usermod -o -u ${UID} www-data \
 && groupmod -o -g ${GID} www-data

COPY php.ini /usr/local/etc/php/conf.d/zz-concord.ini
COPY fpm.conf /usr/local/etc/php-fpm.d/zz-concord.conf

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www

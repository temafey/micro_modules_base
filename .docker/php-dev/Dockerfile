FROM php:8.4-cli-alpine

RUN apk update
RUN apk add --no-cache bash
RUN apk add --no-cache build-base
RUN apk add --no-cache autoconf
RUN apk add --no-cache automake
RUN apk add --no-cache linux-headers

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_MEMORY_LIMIT -1

# php extensions
# intl
RUN apk add --no-cache icu-dev
RUN docker-php-ext-install intl
RUN docker-php-ext-enable intl

# xdebug
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug

RUN mkdir /.composer_cache
ENV COMPOSER_CACHE_DIR /.composer_cache

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ARG PHP_VERSION=8.1
FROM ghcr.io/yiisoft-contrib/php-dev:${PHP_VERSION}

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libmemcached-dev \
        zlib1g-dev \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && rm -rf /var/lib/apt/lists/*

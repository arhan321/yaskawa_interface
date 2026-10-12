
# ============================================================
# RMS YASKAWA - PHP 8.5 + POSTGRESQL
# Laravel | PHP-FPM | Docker | WSL Ubuntu
# ============================================================

FROM php:8.5-fpm-bookworm

ENV COMPOSER_MEMORY_LIMIT=-1 \
    TZ=Asia/Jakarta

# ============================================================
# 1. SYSTEM DEPENDENCIES
# ============================================================

RUN apt-get update && apt-get install -y --no-install-recommends \
    libmemcached-dev \
    libreadline-dev \
    libgmp-dev \
    libzip-dev \
    zlib1g-dev \
    libpq-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libfreetype6-dev \
    libssl-dev \
    libmagickwand-dev \
    libicu-dev \
    libxml2-dev \
    git \
    cron \
    nano \
    curl \
    ca-certificates \
    unzip \
    tzdata \
    && rm -rf /var/lib/apt/lists/*

# ============================================================
# 2. PHP EXTENSIONS
# ============================================================

RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j2 \
        soap \
        exif \
        pcntl \
        intl \
        gmp \
        zip \
        pdo_pgsql \
        pgsql \
        bcmath \
        gd

# ============================================================
# 3. OPCACHE
# PHP 8.5 includes OPcache by default.
# DO NOT run docker-php-ext-install opcache.
# ============================================================

RUN php -m | grep -i "Zend OPcache"

# ============================================================
# 4. REDIS
# ============================================================

RUN pecl install redis \
    && docker-php-ext-enable redis

# ============================================================
# 5. MONGODB
# ============================================================

RUN yes '' | pecl install mongodb \
    && docker-php-ext-enable mongodb

# ============================================================
# 6. IMAGICK
# ============================================================

RUN yes '' | pecl install imagick \
    && docker-php-ext-enable imagick

# ============================================================
# 7. XDEBUG
# Install only. Existing entrypoint controls activation.
# ============================================================

RUN pecl install xdebug

# ============================================================
# 8. MEMCACHED
# ============================================================

RUN yes '' | pecl install memcached \
    && docker-php-ext-enable memcached

# ============================================================
# 9. COMPOSER 2
# ============================================================

COPY --from=composer:2 \
    /usr/bin/composer \
    /usr/local/bin/composer

# ============================================================
# 10. LARAVEL SCHEDULER
# ============================================================

RUN echo '* * * * * root /usr/local/bin/php /var/www/html/artisan schedule:run >> /dev/null 2>&1' \
    > /etc/cron.d/laravel-scheduler \
    && chmod 0644 /etc/cron.d/laravel-scheduler

# ============================================================
# 11. PHP CONFIGURATION
# ============================================================

COPY ./local.ini /usr/local/etc/php/conf.d/local.ini

# ============================================================
# 12. WORKING DIRECTORY
# ============================================================

WORKDIR /var/www/html

# ============================================================
# 13. DOCKER ENTRYPOINT
# ============================================================

COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

# ============================================================
# 14. PHP-FPM
# ============================================================

EXPOSE 9000

CMD ["php-fpm"]

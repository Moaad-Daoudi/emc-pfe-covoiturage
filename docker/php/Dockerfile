FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    bash \
    curl \
    linux-headers

# Install PHP extension installer helper
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install required Symfony & MySQL PHP extensions
RUN install-php-extensions \
    pdo_mysql \
    intl \
    opcache \
    zip \
    apcu

# Configure Morocco timezone & memory limits
RUN echo "date.timezone=Africa/Casablanca" > /usr/local/etc/php/conf.d/timezone.ini \
    && echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini

# Install Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Configure working directory
WORKDIR /var/www/html

# Install automated entrypoint for turnkey 1-command startup (Level 5)
COPY docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]

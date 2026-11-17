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

EXPOSE 9000
CMD ["php-fpm"]

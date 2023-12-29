ARG PHP_VERSION=8.2
ARG PHP_IMAGE=php:${PHP_VERSION}-cli

FROM ${PHP_IMAGE}

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    libpq-dev

# Install PHP Extensions
RUN docker-php-ext-install bcmath \
    && docker-php-ext-install zip

# Install PDO, PDO_MYSQL, PDO_PGSQL
RUN docker-php-ext-install pdo pdo_pgsql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Composer Dependencies
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install

CMD [ "bash" ]

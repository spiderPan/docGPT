ARG PHP_VERSION=8.2
ARG PHP_IMAGE=php:${PHP_VERSION}-cli

FROM ${PHP_IMAGE}

# Install the PostgreSQL client and driver
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql

# Install PDO
RUN docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-install pdo_mysql pdo

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

CMD [ "bash" ]

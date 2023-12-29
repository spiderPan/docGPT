ARG PHP_VERSION=8.2
ARG PHP_IMAGE=php:${PHP_VERSION}-cli

FROM ${PHP_IMAGE}

# Install PHP Extensions PDO, PDO_MYSQL, PDO_PGSQL
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
                       libpq-dev \
                       wget \
                       vim  \
    && \
    docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

CMD [ "bash" ]

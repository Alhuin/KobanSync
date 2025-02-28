FROM php:7.4-apache
# TODO php8

RUN apt-get update && apt-get install -y \
    mariadb-client \
    zip unzip \
    subversion \
    libzip-dev \
    rsync \
    curl \
    gettext

RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/memory-limit.ini

RUN docker-php-ext-install mysqli

RUN curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY wait-for-mysql.sh /usr/local/bin/wait-for-mysql.sh

WORKDIR /var/www/html
ENTRYPOINT [ "entrypoint.sh" ]
CMD [ "apache2-foreground" ]

#!/bin/sh

PHP_VERSION=$1

DEBIAN_VERSION=$(lsb_release -s -c)

if [ "${DEBIAN_VERSION}" = jessie ]; then
    echo "ERROR: we currently do not support custom php versions on Debian Jessie"
    exit 1
fi

DEBIAN_FRONTEND=noninteractive apt-get install -y \
    gnupg2 ca-certificates lsb-release apt-transport-https

wget https://packages.sury.org/php/apt.gpg
apt-key add apt.gpg
echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-json \
    php${PHP_VERSION}-memcached \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-pgsql \
    php${PHP_VERSION}-xdebug \
    php${PHP_VERSION}-xml

update-alternatives --set php /usr/bin/php${PHP_VERSION}

php -v

#!/bin/sh

# Installs required OS packages

# @todo make install of Apache, Java, memcached, mysql-client optional
# @todo allow optional install of redis, postgresql-client, elastic ?
# @todo allow optional install of custom packages

PHP_VERSION=$1
# `lsb-release` is not yet onboard...
DEBIAN_VERSION=$(cat /etc/os-release | grep 'VERSION_CODENAME=' | sed 's/VERSION_CODENAME=//')

if [ "${DEBIAN_VERSION}" = jessie -o -z "${DEBIAN_VERSION}" ]; then
    apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
        apache2 \
        default-jre-headless \
        mysql-client \
        git \
        lsb-release \
        memcached \
        sudo \
        unzip \
        wget \
        zip
else
    apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
        apache2 \
        default-jre-headless \
        default-mysql-client \
        git \
        lsb-release \
        memcached \
        sudo \
        unzip \
        wget \
        zip
fi

if [ "${PHP_VERSION}" = default ]; then
    ./getphp_default.sh
else
    ./getphp_sury.sh "${PHP_VERSION}"
fi

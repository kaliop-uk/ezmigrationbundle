#!/bin/sh

# @todo verify if this list works

apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    apache2 \
    default-jre-headless \
    default-mysql-client \
    git \
    memcached \
    php \
    php-cli \
    php-curl \
    php-gd \
    php-intl \
    php-json \
    php-memcached \
    php-mbstring \
    php-mysql \
    php-xml \
    sudo \
    unzip \
    wget \
    zip

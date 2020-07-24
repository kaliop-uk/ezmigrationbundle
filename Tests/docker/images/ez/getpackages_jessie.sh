#!/bin/sh

apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    apache2 \
    default-jre-headless \
    mysql-client \
    git \
    memcached \
    php5 \
    php5-cli \
    php5-curl \
    php5-gd \
    php5-intl \
    php5-json \
    php5-memcached \
    php5-mysql \
    php5-xsl \
    sudo \
    unzip \
    wget \
    zip

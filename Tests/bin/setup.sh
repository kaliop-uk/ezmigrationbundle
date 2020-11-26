#!/usr/bin/env bash

# Set up fully the test environment (except for installing required sw packages): php, mysql, eZ, etc...
# Has to be useable from Docker as well as from Travis.
#
# Uses env vars: TRAVIS_PHP_VERSION

# @todo check if all required env vars have a value
# @todo support a -v option

set -e

cd $(dirname ${BASH_SOURCE[0]})/../..

# For php 5.6, Composer needs humongous amounts of ram - which we don't have on Travis. Enable swap as workaround
if [ "${TRAVIS_PHP_VERSION}" = "5.6" ]; then
    echo "Setting up a swap file..."

    # @todo any other services we could stop ?
    sudo systemctl stop cron atd docker snapd mysql

    sudo fallocate -l 10G /swapfile
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile
    sudo swapon /swapfile
    sudo swapon -s

    sudo sysctl vm.swappiness=10
    sudo sysctl vm.vfs_cache_pressure=50

    #free -m
    #df -h
    #ps auxwww
    #systemctl list-units --type=service
fi

# This is done by Travis automatically...
#if [ "${TRAVIS}" != "true" ]; then
#    composer selfupdate
#fi

./Tests/bin/setup/php-config.sh

./Tests/bin/setup/composer-dependencies.sh

if [ "${TRAVIS_PHP_VERSION}" = "5.6" ]; then
    sudo systemctl start mysql
fi

# Set up eZ configuration files
./Tests/bin/setup/ez-config.sh

# Create the database from sql files present in either the legacy stack or kernel (has to be run after composer install)
./Tests/bin/create-db.sh

# TODO are these needed at all?
#$(dirname ${BASH_SOURCE[0]})/sfconsole.sh assetic:dump
#$(dirname ${BASH_SOURCE[0]})/sfconsole.sh cache:clear --no-debug

# TODO for eZPlatform, do we need to set up SOLR as well ?
#if [ "$EZ_VERSION" != "ezpublish" ]; then ./vendor/ezsystems/ezplatform-solr-search-engine && bin/.travis/init_solr.sh; fi

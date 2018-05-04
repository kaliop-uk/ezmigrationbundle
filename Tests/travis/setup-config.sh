#!/usr/bin/env bash

EZ_VERSION=$1
EZ_APP_DIR=$2
EZ_KERNEL=$3
INSTALL_TAGSBUNDLE=$4

if [ "$EZ_VERSION" = "ezplatform2" ]; then
    APP_DIR=vendor/ezsystems/ezplatform/${EZ_APP_DIR}
else
    APP_DIR=vendor/ezsystems/${EZ_VERSION}/${EZ_APP_DIR}
fi

# Set up configuration files:
# eZ5 config files
cp ${APP_DIR}/config/parameters.yml.dist ${APP_DIR}/config/parameters.yml
cat Tests/ezpublish/config/config_behat_${EZ_VERSION}.yml >> ${APP_DIR}/config/config_behat.yml

# Load the migration bundle in the Sf kernel
sed -i 's/$bundles = array(/$bundles = array(new Kaliop\\eZMigrationBundle\\EzMigrationBundle(),/' ${APP_DIR}/${EZ_KERNEL}.php
# And optionally the Netgen tags bundle
if [ "$INSTALL_TAGSBUNDLE" = "1" ]; then
    # we have to load netgen tags bundle after the Kernel bundles... hopefully OneupFlysystemBundle will stay there :-)
    sed -i 's/OneupFlysystemBundle(),\?/OneupFlysystemBundle(), new Netgen\\TagsBundle\\NetgenTagsBundle(),/' ${APP_DIR}/${EZ_KERNEL}.php
fi
# And optionally the EzCoreExtraBundle bundle - not needed any more ?
#if grep -q 'lolautruche/ez-core-extra-bundle' composer.lock; then
#    sed -i 's/OneupFlysystemBundle(),\?/OneupFlysystemBundle(), new Lolautruche\\EzCoreExtraBundle\\EzCoreExtraBundle(),/' ${APP_DIR}/${EZ_KERNEL}.php
#fi

# For eZPlatform, load the xmltext bundle
if [ "$EZ_VERSION" = "ezplatform" -o "$EZ_VERSION" = "ezplatform2" ]; then
    # we have to load netgen tags bundle after the Kernel bundles... hopefully OneupFlysystemBundle will stay there :-)
    sed -i 's/AppBundle(),\?/AppBundle(), new EzSystems\\EzPlatformXmlTextFieldTypeBundle\\EzSystemsEzPlatformXmlTextFieldTypeBundle (),/' ${APP_DIR}/${EZ_KERNEL}.php
fi
# Fix the eZ5 autoload configuration for the unexpected directory layout
sed -i "s#'/../vendor/autoload.php'#'/../../../../vendor/autoload.php'#" ${APP_DIR}/autoload.php

# Generate legacy autoloads
if [ "$EZ_VERSION" = "ezpublish-community" ]; then cat Tests/ezpublish-legacy/config.php > vendor/ezsystems/ezpublish-legacy/config.php; fi
if [ "$EZ_VERSION" = "ezpublish-community" ]; then cd vendor/ezsystems/ezpublish-legacy && php bin/php/ezpgenerateautoloads.php && cd ../../..; fi

# Fix the phpunit configuration if needed
if [ "$EZ_VERSION" = "ezplatform" -o "$EZ_VERSION" = "ezplatform2" ]; then sed -i 's/"vendor\/ezsystems\/ezpublish-community\/ezpublish"/"vendor\/ezsystems\/ezplatform\/app"/' phpunit.xml; fi

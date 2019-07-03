#!/usr/bin/env bash

# Uses env vars: EZ_VERSION, INSTALL_TAGSBUNDLE, EZ_APP_DIR, EZ_KERNEL

# @todo check if all required vars have a value

if [ "${EZ_VERSION}" = "ezplatform2" ]; then
    APP_DIR=vendor/ezsystems/ezplatform/${EZ_APP_DIR}
else
    APP_DIR=vendor/ezsystems/${EZ_VERSION}/${EZ_APP_DIR}
fi

# hopefully these bundles will stay there :-) it is important that they are loaded after the kernel ones...
if [ "${EZ_VERSION}" = "ezplatform" -o "${EZ_VERSION}" = "ezplatform2" ]; then
    LAST_BUNDLE=AppBundle
else
    LAST_BUNDLE=OneupFlysystemBundle
fi

# Set up configuration files
# eZ5 config files
cp ${APP_DIR}/config/parameters.yml.dist ${APP_DIR}/config/parameters.yml
cat Tests/ezpublish/config/config_behat_${EZ_VERSION}.yml >> ${APP_DIR}/config/config_behat.yml

# Load the migration bundle in the Sf kernel
sed -i 's/$bundles = array(/$bundles = array(new Kaliop\\eZMigrationBundle\\EzMigrationBundle(),/' ${APP_DIR}/${EZ_KERNEL}.php
sed -i 's/$bundles = \[/$bundles = \[new Kaliop\\eZMigrationBundle\\EzMigrationBundle(),/' ${APP_DIR}/${EZ_KERNEL}.php

# And optionally the EzCoreExtraBundle bundle
#if grep -q '"name": "lolautruche/ez-core-extra-bundle",' composer.lock; then
if [ "${EZ_VERSION}" = "ezplatform2" ]; then
    sed -i "/${LAST_BUNDLE}()/i new Lolautruche\\\\\EzCoreExtraBundle\\\\\EzCoreExtraBundle()," ${APP_DIR}/${EZ_KERNEL}.php
fi

# And optionally the Netgen tags bundle
if [ "${INSTALL_TAGSBUNDLE}" = "1" ]; then
    sed -i "/${LAST_BUNDLE}()/i new Netgen\\\\\TagsBundle\\\\\NetgenTagsBundle()," ${APP_DIR}/${EZ_KERNEL}.php
fi

# For eZPlatform, load the xmltext bundle
if [ "${EZ_VERSION}" = "ezplatform" -o "${EZ_VERSION}" = "ezplatform2" ]; then
    sed -i "/${LAST_BUNDLE}()/i new EzSystems\\\\\EzPlatformXmlTextFieldTypeBundle\\\\\EzSystemsEzPlatformXmlTextFieldTypeBundle()," ${APP_DIR}/${EZ_KERNEL}.php
fi

# Fix the eZ5 autoload configuration for the unexpected directory layout
if [ -f "${APP_DIR}/autoload.php" ]; then
    sed -i "s#'/../vendor/autoload.php'#'/../../../../vendor/autoload.php'#" ${APP_DIR}/autoload.php
fi

# as well as the config for jms_translation
sed -i "s#'%kernel.root_dir%/../vendor/ezsystems/ezplatform-admin-ui/src#'%kernel.root_dir%/../../ezplatform-admin-ui/src#" ${APP_DIR}/config/config.yml
sed -i "s#'%kernel.root_dir%/../vendor/ezsystems/ezplatform-admin-ui-modules/src#'%kernel.root_dir%/../../ezplatform-admin-ui-modules/src#" ${APP_DIR}/config/config.yml

# Fix the eZ console autoload config if needed (ezplatform 2)
if [ -f vendor/ezsystems/ezplatform/bin/console ]; then
    sed -i "s#'/../vendor/autoload.php'#'/../../../../vendor/autoload.php'#" vendor/ezsystems/ezplatform/bin/console
fi

# Generate legacy autoloads
if [ "${EZ_VERSION}" = "ezpublish-community" ]; then
    cat Tests/ezpublish-legacy/config.php > vendor/ezsystems/ezpublish-legacy/config.php
    cd vendor/ezsystems/ezpublish-legacy && php bin/php/ezpgenerateautoloads.php && cd ../../..
fi

# Fix the phpunit configuration if needed
if [ "${EZ_VERSION}" = "ezplatform" -o "${EZ_VERSION}" = "ezplatform2" ]; then sed -i 's/"vendor\/ezsystems\/ezpublish-community\/ezpublish"/"vendor\/ezsystems\/ezplatform\/app"/' phpunit.xml.dist; fi

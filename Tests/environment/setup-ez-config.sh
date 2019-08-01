#!/usr/bin/env bash

# Set up configuration files

# Uses env vars: EZ_VERSION, KERNEL_DIR, INSTALL_TAGSBUNDLE

# @todo check if all required vars have a value

if [ "${EZ_VERSION}" = "ezplatform3" ]; then
    APP_DIR=vendor/ezsystems/ezplatform/src
    CONFIG_DIR=vendor/ezsystems/ezplatform/config
    EZ_KERNEL=Kernel
elif [ "${EZ_VERSION}" = "ezplatform2" ]; then
    APP_DIR=vendor/ezsystems/ezplatform/app
    CONFIG_DIR=${APP_DIR}/config
    EZ_KERNEL=AppKernel
elif [ "${EZ_VERSION}" = "ezplatform" ]; then
    APP_DIR=vendor/ezsystems/ezplatform/app
    CONFIG_DIR=${APP_DIR}/config
    EZ_KERNEL=AppKernel
elif [ "${EZ_VERSION}" = "ezpublish-community" ]; then
    APP_DIR=vendor/ezsystems/${EZ_VERSION}/ezpublish
    CONFIG_DIR=${APP_DIR}/config
    EZ_KERNEL=EzPublishKernel
else
    echo "Unsupported eZ version: ${EZ_VERSION}"
    exit 1
fi

# hopefully these bundles will stay there :-) it is important that they are loaded after the kernel ones...
if [ "${EZ_VERSION}" = "ezplatform" -o "${EZ_VERSION}" = "ezplatform2" ]; then
    LAST_BUNDLE=AppBundle
else
    LAST_BUNDLE=OneupFlysystemBundle
fi

### @todo add support for ezplatform 3 config

# eZ5/eZPlatform config files
cp ${CONFIG_DIR}/parameters.yml.dist ${CONFIG_DIR}/parameters.yml
if [ ! -f ${CONFIG_DIR}/config_behat_orig.yml ]; then
    mv ${CONFIG_DIR}/config_behat.yml ${CONFIG_DIR}/config_behat_orig.yml
    cp Tests/ezpublish/config/config_behat_${EZ_VERSION}.yml ${CONFIG_DIR}/config_behat.yml
    cp Tests/ezpublish/config/config_behat.php ${CONFIG_DIR}/config_behat.php
    if [ -f Tests/ezpublish/config/ezpublish_behat_${EZ_VERSION}.yml ]; then
        mv ${CONFIG_DIR}/ezpublish_behat.yml ${CONFIG_DIR}/ezpublish_behat_orig.yml
        cp Tests/ezpublish/config/ezpublish_behat_${EZ_VERSION}.yml ${CONFIG_DIR}/ezpublish_behat.yml
    fi
fi

# Load the migration bundle in the Sf kernel
fgrep -q 'new Kaliop\eZMigrationBundle\EzMigrationBundle()' ${KERNEL_DIR}/${EZ_KERNEL}.php
if [ $? -ne 0 ]; then
    sed -i 's/$bundles = array(/$bundles = array(new Kaliop\\eZMigrationBundle\\EzMigrationBundle(),/' ${KERNEL_DIR}/${EZ_KERNEL}.php
    sed -i 's/$bundles = \[/$bundles = \[new Kaliop\\eZMigrationBundle\\EzMigrationBundle(),/' ${KERNEL_DIR}/${EZ_KERNEL}.php
fi

# And optionally the EzCoreExtraBundle bundle
if [ "${EZ_VERSION}" = "ezplatform2" ]; then
    fgrep -q 'new Lolautruche\EzCoreExtraBundle\EzCoreExtraBundle()' ${KERNEL_DIR}/${EZ_KERNEL}.php
    if [ $? -ne 0 ]; then
        sed -i "/${LAST_BUNDLE}()/i new Lolautruche\\\\\EzCoreExtraBundle\\\\\EzCoreExtraBundle()," ${KERNEL_DIR}/${EZ_KERNEL}.php
    fi
fi

# And optionally the Netgen tags bundle
if [ "${INSTALL_TAGSBUNDLE}" = "1" ]; then
    fgrep -q 'new Netgen\TagsBundle\NetgenTagsBundle()' ${KERNEL_DIR}/${EZ_KERNEL}.php
    if [ $? -ne 0 ]; then
        sed -i "/${LAST_BUNDLE}()/i new Netgen\\\\\TagsBundle\\\\\NetgenTagsBundle()," ${KERNEL_DIR}/${EZ_KERNEL}.php
    fi
fi

# For eZPlatform, load the xmltext bundle
if [ "${EZ_VERSION}" = "ezplatform" -o "${EZ_VERSION}" = "ezplatform2" ]; then
    fgrep -q 'new EzSystems\EzPlatformXmlTextFieldTypeBundle\EzSystemsEzPlatformXmlTextFieldTypeBundle()' ${KERNEL_DIR}/${EZ_KERNEL}.php
    if [ $? -ne 0 ]; then
        sed -i "/${LAST_BUNDLE}()/i new EzSystems\\\\\EzPlatformXmlTextFieldTypeBundle\\\\\EzSystemsEzPlatformXmlTextFieldTypeBundle()," ${KERNEL_DIR}/${EZ_KERNEL}.php
    fi
fi

# Fix the eZ5/eZPlatform autoload configuration for the unexpected directory layout
if [ -f "${APP_DIR}/autoload.php" ]; then
    sed -i "s#'/../vendor/autoload.php'#'/../../../../vendor/autoload.php'#" ${APP_DIR}/autoload.php
fi

# as well as the config for jms_translation
if [ -f ${CONFIG_DIR}/config.yml ]; then
    sed -i "s#'%kernel.root_dir%/../vendor/ezsystems/ezplatform-admin-ui/src#'%kernel.root_dir%/../../ezplatform-admin-ui/src#" ${CONFIG_DIR}/config.yml
    sed -i "s#'%kernel.root_dir%/../vendor/ezsystems/ezplatform-admin-ui-modules/src#'%kernel.root_dir%/../../ezplatform-admin-ui-modules/src#" ${CONFIG_DIR}/config.yml
fi

# Fix the eZ console autoload config if needed (ezplatform 2 and ezplatform 3)
if [ -f vendor/ezsystems/ezplatform/bin/console ]; then
    sed -i "s#'/../vendor/autoload.php'#'/../../../../vendor/autoload.php'#" vendor/ezsystems/ezplatform/bin/console
    sed -i "s#dirname(__DIR__).'/vendor/autoload.php'#'dirname(__DIR__).'/../../../vendor/autoload.php''#" vendor/ezsystems/ezplatform/bin/console
fi

# Set up legacy settings and generate legacy autoloads
if [ "${EZ_VERSION}" = "ezpublish-community" ]; then
    cat Tests/ezpublish-legacy/config.php > vendor/ezsystems/ezpublish-legacy/config.php
    cd vendor/ezsystems/ezpublish-legacy && php bin/php/ezpgenerateautoloads.php && cd ../../..
fi

# Fix the phpunit configuration if needed
if [ "${EZ_VERSION}" = "ezplatform" -o "${EZ_VERSION}" = "ezplatform2" ]; then
    sed -i 's/"vendor\/ezsystems\/ezpublish-community\/ezpublish"/"vendor\/ezsystems\/ezplatform\/app"/' phpunit.xml.dist
elif [ "${EZ_VERSION}" = "ezplatform3" ]; then
    sed -i 's/"vendor\/ezsystems\/ezpublish-community\/ezpublish"/"vendor\/ezsystems\/ezplatform\/src"/' phpunit.xml.dist
fi

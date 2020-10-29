#!/usr/bin/env bash

# Set up eZ configuration files
#
# Uses env vars: EZ_VERSION, KERNEL_CLASS, KERNEL_DIR, INSTALL_SOLRBUNDLE, INSTALL_TAGSBUNDLE

# @todo check if all required vars have a value
# @todo use 'set -e' to insure a proper setup

source $(dirname ${BASH_SOURCE[0]})/set-env-vars.sh

if [ "${EZ_VERSION}" = "ezplatform3" ]; then
    APP_DIR=vendor/ezsystems/ezplatform
    CONFIG_DIR=${APP_DIR}/config
elif [ "${EZ_VERSION}" = "ezplatform2" ]; then
    APP_DIR=vendor/ezsystems/ezplatform
    CONFIG_DIR=${APP_DIR}/app/config
elif [ "${EZ_VERSION}" = "ezplatform" ]; then
    APP_DIR=vendor/ezsystems/ezplatform
    CONFIG_DIR=${APP_DIR}/app/config
elif [ "${EZ_VERSION}" = "ezpublish-community" ]; then
    APP_DIR=vendor/ezsystems/ezpublish-community
    CONFIG_DIR=${APP_DIR}/ezpublish/config
else
    echo "Unsupported eZ version: ${EZ_VERSION}"
    exit 1
fi

# hopefully these bundles will stay there :-) it is important that they are loaded after the kernel ones...
if [ "${EZ_VERSION}" = "ezplatform3" ]; then
    LAST_BUNDLE=Overblog\GraphiQLBundle\OverblogGraphiQLBundle
elif [ "${EZ_VERSION}" = "ezplatform" -o "${EZ_VERSION}" = "ezplatform2" ]; then
    LAST_BUNDLE=AppBundle
else
    LAST_BUNDLE=OneupFlysystemBundle
fi

# eZ5/eZPlatform config files
if [ -f ${CONFIG_DIR}/parameters.yml.dist ]; then
    cp ${CONFIG_DIR}/parameters.yml.dist ${CONFIG_DIR}/parameters.yml
fi
if [ -f Tests/config/${EZ_VERSION}/config_behat.yml ]; then
    # @todo if config_behat_orig.yml exists, rename it as well
    grep -q 'config_behat_orig.yml' ${CONFIG_DIR}/config_behat.yml || mv ${CONFIG_DIR}/config_behat.yml ${CONFIG_DIR}/config_behat_orig.yml
    cp Tests/config/${EZ_VERSION}/config_behat.yml ${CONFIG_DIR}/config_behat.yml
fi
cp Tests/config/common/config_behat.php ${CONFIG_DIR}/config_behat.php
if [ -f Tests/config/${EZ_VERSION}/ezpublish_behat.yml ]; then
    grep -q 'ezpublish_behat_orig.yml' ${CONFIG_DIR}/ezpublish_behat.yml || mv ${CONFIG_DIR}/ezpublish_behat.yml ${CONFIG_DIR}/ezpublish_behat_orig.yml
    cp Tests/config/${EZ_VERSION}/ezpublish_behat.yml ${CONFIG_DIR}/ezpublish_behat.yml
fi
if [ -f Tests/config/${EZ_VERSION}/ezplatform.yml ]; then
    mv ${CONFIG_DIR}/packages/behat/ezplatform.yaml ${CONFIG_DIR}/packages/behat/ezplatform_orig.yaml
    cp Tests/config/${EZ_VERSION}/ezplatform.yml ${CONFIG_DIR}/packages/behat/ezplatform.yaml
fi

# Load the migration bundle in the Sf kernel
fgrep -q 'new Kaliop\eZMigrationBundle\EzMigrationBundle()' ${KERNEL_DIR}/${KERNEL_CLASS}.php
if [ $? -ne 0 ]; then
    sed -i 's/$bundles = array(/$bundles = array(new Kaliop\\eZMigrationBundle\\EzMigrationBundle(),/' ${KERNEL_DIR}/${KERNEL_CLASS}.php
    sed -i 's/$bundles = \[/$bundles = \[new Kaliop\\eZMigrationBundle\\EzMigrationBundle(),/' ${KERNEL_DIR}/${KERNEL_CLASS}.php
fi

# And optionally the EzCoreExtraBundle bundle
if [ "${EZ_VERSION}" = "ezplatform2" ]; then
    fgrep -q 'new Lolautruche\EzCoreExtraBundle\EzCoreExtraBundle()' ${KERNEL_DIR}/${KERNEL_CLASS}.php
    if [ $? -ne 0 ]; then
        sed -i "/${LAST_BUNDLE}()/i new Lolautruche\\\\\EzCoreExtraBundle\\\\\EzCoreExtraBundle()," ${KERNEL_DIR}/${KERNEL_CLASS}.php
    fi
fi

# And optionally the Netgen tags bundle
if [ "${INSTALL_TAGSBUNDLE}" = "1" ]; then
    fgrep -q 'new Netgen\TagsBundle\NetgenTagsBundle()' ${KERNEL_DIR}/${KERNEL_CLASS}.php
    if [ $? -ne 0 ]; then
        sed -i "/${LAST_BUNDLE}()/i new Netgen\\\\\TagsBundle\\\\\NetgenTagsBundle()," ${KERNEL_DIR}/${KERNEL_CLASS}.php
    fi
fi

if [ "${INSTALL_SOLRBUNDLE}" = "1" ]; then
    fgrep -q 'new EzSystems\EzPlatformSolrSearchEngineBundle\EzSystemsEzPlatformSolrSearchEngineBundle()' ${KERNEL_DIR}/${KERNEL_CLASS}.php
    if [ $? -ne 0 ]; then
        sed -i "/${LAST_BUNDLE}()/i new EzSystems\\\\\EzPlatformSolrSearchEngineBundle\\\\\EzSystemsEzPlatformSolrSearchEngineBundle()," ${KERNEL_DIR}/${KERNEL_CLASS}.php
    fi
fi

# For eZPlatform, load the xmltext bundle
if [ "${EZ_VERSION}" = "ezplatform" -o "${EZ_VERSION}" = "ezplatform2" ]; then
    fgrep -q 'new EzSystems\EzPlatformXmlTextFieldTypeBundle\EzSystemsEzPlatformXmlTextFieldTypeBundle()' ${KERNEL_DIR}/${KERNEL_CLASS}.php
    if [ $? -ne 0 ]; then
        sed -i "/${LAST_BUNDLE}()/i new EzSystems\\\\\EzPlatformXmlTextFieldTypeBundle\\\\\EzSystemsEzPlatformXmlTextFieldTypeBundle()," ${KERNEL_DIR}/${KERNEL_CLASS}.php
    fi
fi

# Fix the eZ5/eZPlatform autoload configuration for the unexpected directory layout
if [ -f "${KERNEL_DIR}/autoload.php" ]; then
    sed -i "s#'/../vendor/autoload.php'#'/../../../../vendor/autoload.php'#" ${KERNEL_DIR}/autoload.php
fi

# and the one for eZPlatform 3
if [ -f ${CONFIG_DIR}/bootstrap.php ]; then
  sed -i "s#dirname(__DIR__).'/vendor/autoload.php'#dirname(__DIR__).'/../../../vendor/autoload.php'#" ${CONFIG_DIR}/bootstrap.php
fi

# as well as the config for jms_translation
# @todo can't we just override these values instead of hacking the original files?
if [ -f ${CONFIG_DIR}/config.yml ]; then
    sed -i "s#'%kernel.root_dir%/../vendor/ezsystems/ezplatform-admin-ui/src#'%kernel.root_dir%/../../ezplatform-admin-ui/src#" ${CONFIG_DIR}/config.yml
    sed -i "s#'%kernel.root_dir%/../vendor/ezsystems/ezplatform-admin-ui-modules/src#'%kernel.root_dir%/../../ezplatform-admin-ui-modules/src#" ${CONFIG_DIR}/config.yml
fi
if [ -f ${CONFIG_DIR}/packages/ezplatform_admin_ui.yaml ]; then
    sed -i "s#'%kernel.root_dir%/../vendor/ezsystems/ezplatform-admin-ui/src#'%kernel.root_dir%/../../ezplatform-admin-ui/src#" ${CONFIG_DIR}/packages/ezplatform_admin_ui.yaml
    sed -i "s#'%kernel.root_dir%/../vendor/ezsystems/ezplatform-admin-ui/src/bundle/Resources/translations/#'%kernel.root_dir%/../../ezplatform-admin-ui/src/bundle/Resources/translations/#" ${CONFIG_DIR}/packages/ezplatform_admin_ui.yaml
fi
if [ -f ${CONFIG_DIR}/packages/ezplatform_admin_ui_modules.yaml ]; then
    sed -i "s#'%kernel.root_dir%/../vendor/ezsystems/ezplatform-admin-ui-modules/src#'%kernel.root_dir%/../../ezplatform-admin-ui-modules/src#" ${CONFIG_DIR}/packages/ezplatform_admin_ui_modules.yaml
    sed -i "s#'%kernel.root_dir%/../vendor/ezsystems/ezplatform-admin-ui-modules/Resources/translations/#'%kernel.root_dir%/../../ezplatform-admin-ui-modules/Resources/translations/#" ${CONFIG_DIR}/packages/ezplatform_admin_ui_modules.yaml
fi

# Fix the eZ console autoload config if needed (ezplatform 2 and ezplatform 3)
if [ -f ${APP_DIR}/bin/console ]; then
    sed -i "s#'/../vendor/autoload.php'#'/../../../../vendor/autoload.php'#" ${APP_DIR}/bin/console
    sed -i "s#dirname(__DIR__).'/vendor/autoload.php'#dirname(__DIR__).'/../../../vendor/autoload.php'#" ${APP_DIR}/bin/console
fi

# Set up legacy settings and generate legacy autoloads
if [ "${EZ_VERSION}" = "ezpublish-community" ]; then
    cat Tests/config/ezpublish-legacy/config.php > vendor/ezsystems/ezpublish-legacy/config.php
    cd vendor/ezsystems/ezpublish-legacy && php bin/php/ezpgenerateautoloads.php && cd ../../..
fi

# Fix the phpunit configuration if needed
if [ "${EZ_VERSION}" = "ezplatform" -o "${EZ_VERSION}" = "ezplatform2" ]; then
    sed -i 's/"vendor\/ezsystems\/ezpublish-community\/ezpublish"/"vendor\/ezsystems\/ezplatform\/app"/' phpunit.xml.dist
elif [ "${EZ_VERSION}" = "ezplatform3" ]; then
    sed -i 's/"vendor\/ezsystems\/ezpublish-community\/ezpublish"/"vendor\/ezsystems\/ezplatform\/src"/' phpunit.xml.dist
fi

#!/usr/bin/env bash

# Install dependencies using Composer
#
# Uses env vars: EZ_COMPOSER_LOCK, EZ_PACKAGES

# We do not rely on the requirements set in composer.json, but install a different eZ version depending on the test matrix (env vars)

# For the moment, to install eZPlatform, a set of DEV packages has to be allowed (eg roave/security-advisories); really ugly sed expression to alter composer.json follows
# A different work around for this has been found in setting up an alias for them in the std composer.json require-dev section
#- 'if [ "$EZ_VERSION" != "ezpublish" ]; then sed -i ''s/"license": "GPL-2.0",/"license": "GPL-2.0", "minimum-stability": "dev", "prefer-stable": true,/'' composer.json; fi'

# Allow installing a precomputed set of packages. Useful to save memory, eg. for running with php 5.6...
if [ -n "${EZ_COMPOSER_LOCK}" ]; then
    echo "Installing packages via Composer using existing lock file ${EZ_COMPOSER_LOCK}..."

    cp ${EZ_COMPOSER_LOCK} composer.lock
    composer install
else
    echo "Installing packages via Composer: the ones in composer.json plus ${EZ_PACKAGES}..."

    # composer.lock gets in the way when switching between eZ versions
    if [ -f composer.lock ]; then
        rm composer.lock
    fi
    cp composer.json composer.json.bak
    # we split require from update to (hopefully) save some ram
    composer require --dev --no-update ${EZ_PACKAGES}
    cp composer.json composer_last.json
    composer update --dev
    # @todo remove composer.json.bak ? (we should also check that no-one has modified it since we saved it...)
    cp composer.json.bak composer.json
fi

if [ "${TRAVIS}" = "true" ]; then
    # useful for troubleshooting tests failures
    composer show
fi

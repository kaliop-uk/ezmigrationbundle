#!/usr/bin/env bash

# Set up PHP configuration files
#
# Uses env vars: TRAVIS

# Increase php memory limit (need to do this now or we risk composer failing)
if [ "${TRAVIS}" = "true" ]; then
    phpenv config-add Tests/config/php/zzz_php.ini
else
    INI_PATH=$(php -i | grep 'Scan this dir for additional .ini files')
    INI_PATH=${INI_PATH/Scan this dir for additional .ini files => /}
    sudo cp Tests/config/php/zzz_php.ini ${INI_PATH}
fi

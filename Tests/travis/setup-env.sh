#!/usr/bin/env bash

EZ_VERSION=$1

# Set up env vars:

export SYMFONY_ENV=behat

if [ "$EZ_VERSION" = "ezpublish-community" ]; then
    export KERNEL_DIR=vendor/ezsystems/ezpublish-community/ezpublish
fi
if [ "$EZ_VERSION" = "ezplatform" -o "$EZ_VERSION" = "ezplatform2" ]; then
    export KERNEL_DIR=vendor/ezsystems/ezplatform/app
fi

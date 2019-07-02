#!/usr/bin/env bash

# Set up env vars

# @todo check if all required vars have a value

EZ_VERSION=$1

export SYMFONY_ENV=behat

if [ "$EZ_VERSION" = "ezpublish-community" ]; then
    export KERNEL_DIR=vendor/ezsystems/ezpublish-community/ezpublish
fi
if [ "$EZ_VERSION" = "ezplatform" -o "$EZ_VERSION" = "ezplatform2" ]; then
    export KERNEL_DIR=vendor/ezsystems/ezplatform/app
fi

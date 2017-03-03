#!/usr/bin/env bash

EZ_VERSION=$1

# Set up env vars:

export SYMFONY_ENV=behat

if [ "$EZ_VERSION" = "ezplatform" ]; then
  export KERNEL_DIR=vendor/ezsystems/ezplatform/app
else
  export KERNEL_DIR=vendor/ezsystems/ezpublish-community/ezpublish
fi

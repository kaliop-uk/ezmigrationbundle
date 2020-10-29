#!/usr/bin/env bash

# Set up env vars (if not already set):
# - KERNEL_CLASS, KERNEL_DIR (used by phpunit)
# - CONSOLE_CMD (used by shell scripts)
#
# Uses env vars: EZ_VERSION, EZ_PACKAGES
#
# To be executed using 'source'

# @todo also set up INSTALL_SOLRBUNDLE, INSTALL_TAGSBUNDLE (from EZ_PACKAGES), APP_ENV, SYMFONY_ENV (defaulting to 'behat')

if [ "${EZ_VERSION}" = "ezplatform3" ]; then
    if [ -z "${KERNEL_CLASS}" ]; then
        export KERNEL_CLASS=Kernel
    fi
    if [ -z "${KERNEL_DIR}" ]; then
        export KERNEL_DIR=vendor/ezsystems/ezplatform/app
    fi
    if [ -z "${CONSOLE_CMD}" ]; then
        export CONSOLE_CMD=vendor/ezsystems/ezplatform/bin/console
    fi
elif [ "${EZ_VERSION}" = "ezplatform2" ]; then
    if [ -z "${KERNEL_CLASS}" ]; then
        export KERNEL_CLASS=AppKernel
    fi
    if [ -z "${KERNEL_DIR}" ]; then
        export KERNEL_DIR=vendor/ezsystems/ezplatform/app
    fi
    if [ -z "${CONSOLE_CMD}" ]; then
        export CONSOLE_CMD=vendor/ezsystems/ezplatform/bin/console
    fi
elif [ "${EZ_VERSION}" = "ezplatform" ]; then
    if [ -z "${KERNEL_CLASS}" ]; then
        export KERNEL_CLASS=AppKernel
    fi
    if [ -z "${KERNEL_DIR}" ]; then
        export KERNEL_DIR=vendor/ezsystems/ezplatform/app
    fi
    if [ -z "${CONSOLE_CMD}" ]; then
        export CONSOLE_CMD=vendor/ezsystems/ezplatform/app/console
    fi
elif [ "${EZ_VERSION}" = "ezpublish-community" ]; then
    if [ -z "${KERNEL_CLASS}" ]; then
        export KERNEL_CLASS=EzPublishKernel
    fi
    if [ -z "${KERNEL_DIR}" ]; then
        export KERNEL_DIR=vendor/ezsystems/ezpublish-community/ezpublish
    fi
    if [ -z "${CONSOLE_CMD}" ]; then
        export CONSOLE_CMD=vendor/ezsystems/ezpublish-community/ezpublish/console
    fi
else
    echo "Unsupported eZ version: ${EZ_VERSION}"
    exit 1
fi

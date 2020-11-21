#!/usr/bin/env bash

# Set up env vars (if not already set):
# - KERNEL_CLASS, KERNEL_DIR (used by phpunit)
# - CONSOLE_CMD (used by shell scripts)
#
# Uses env vars: EZ_VERSION
#
# To be executed using 'source'

# @todo also set up APP_ENV, SYMFONY_ENV (defaulting to 'behat')

# Figure out EZ_VERSION from EZ_PACKAGES if the former is not set
if [ -z "${EZ_VERSION}" -a -n "${EZ_PACKAGES}" ]; then
    # @todo...
    :
fi

# @todo Figure out EZ_BUNDLES from EZ_PACKAGES if the former is not set
if [ -z "${EZ_BUNDLES}" -a -n "${EZ_PACKAGES}" ]; then
    # @todo...
    :
fi

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
    printf "\n\e[31mERROR: unsupported eZ version: ${EZ_VERSION}\e[0m\n\n" >&2
    exit 1
fi

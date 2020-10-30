#!/usr/bin/env bash

set -e

# Uses env vars: EZ_VERSION

if [ "${EZ_VERSION}" = "ezplatform3" ]; then
    if [ -z "${VAR_DIR}" ]; then
        VAR_DIR=vendor/ezsystems/ezplatform/var
    fi
elif [ "${EZ_VERSION}" = "ezplatform2" ]; then
    if [ -z "${VAR_DIR}" ]; then
        VAR_DIR=vendor/ezsystems/ezplatform/var
    fi
elif [ "${EZ_VERSION}" = "ezplatform" ]; then
    if [ -z "${VAR_DIR}" ]; then
        VAR_DIR=vendor/ezsystems/ezplatform/var
    fi
elif [ "${EZ_VERSION}" = "ezpublish-community" ]; then
    if [ -z "${VAR_DIR}" ]; then
        VAR_DIR=vendor/ezsystems/ezpublish-community/ezpublish
    fi
    if [ -z "${LEGACY_VAR_DIR}" ]; then
        LEGACY_VAR_DIR=vendor/ezsystems/ezpublish-community-legacy/var
    fi
else
    echo "Unsupported eZ version: ${EZ_VERSION}" >&2
    exit 1
fi

# @todo find

case "${1}" in
    ez-cache | cache)
        rm -rf ${VAR_DIR}/cache/*
        if [ -n "${LEGACY_VAR_DIR}" ]; then
            rm -rf ${LEGACY_VAR_DIR}/cache/*
            rm -rf ${LEGACY_VAR_DIR}/*/cache/*
        fi
    ;;
    ez-logs | logs)
        rm -rf ${VAR_DIR}/logs/*
        if [ -n "${LEGACY_VAR_DIR}" ]; then
            rm -rf ${LEGACY_VAR_DIR}/cache/*
            rm -rf ${LEGACY_VAR_DIR}/*/log/*
        fi
    ;;
    *)
        printf "\n\e[31mERROR: unknown cleanup target\e[0m\n\n" >&2
        exit 1
esac

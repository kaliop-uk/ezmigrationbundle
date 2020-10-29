#!/usr/bin/env bash

set -e

source $(dirname ${BASH_SOURCE[0]})/set-env-vars.sh

VERBOSITY=
RESET=false

while getopts "vr" opt
do
    case $opt in
        r)
            RESET=true
        ;;
        v)
            VERBOSITY=-v
        ;;
    esac
done
shift $((OPTIND-1))

if [ "${RESET}" = true ]; then
    $(dirname ${BASH_SOURCE[0]})/create-db.sh
    $(dirname ${BASH_SOURCE[0]})/sfconsole.sh ${VERBOSITY} cache:clear
fi

# Note: make sure we run the version of phpunit we installed, not the system one. See: https://github.com/sebastianbergmann/phpunit/issues/2014

$(dirname $(dirname $(dirname ${BASH_SOURCE[0]})))/vendor/phpunit/phpunit/phpunit --stderr --colors ${VERBOSITY} Tests/phpunit "$@"

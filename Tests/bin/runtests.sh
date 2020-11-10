#!/usr/bin/env bash

set -e

source $(dirname ${BASH_SOURCE[0]})/set-env-vars.sh

PHPOPTS=
COVERAGE=
TESTSUITE=Tests/phpunit
RESET=false
VERBOSITY=

while getopts ":c:vr" opt
do
    case $opt in
        c)
            # @todo parse $OPTARG, decide format to use for coverage based on file/dir name (not easy to do)
            COVERAGE="--coverage-clover=${OPTARG}"
            PHPOPTS="-d zend_extension=xdebug.so"
        ;;
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
    echo "Resetting the database..."
    $(dirname ${BASH_SOURCE[0]})/create-db.sh
    echo "Purging eZ caches..."
    # Some manipulations make the SF console fail to run - that's why we prefer to clear the cache via file purge
    #$(dirname ${BASH_SOURCE[0]})/sfconsole.sh ${VERBOSITY} cache:clear
    $(dirname ${BASH_SOURCE[0]})/cleanup.sh ez-cache
    echo "Running the tests..."
fi

# Try to be smart parsing the cli params:
# - if there are only options and no args, do not unset TESTSUITE
# - if there are code coverage options, make sure we enable xdebug
if [ -n "$*" ]; then
    for ARG in "$@"
    do
        case "$ARG" in
        --coverage-*)
            PHPOPTS="-d zend_extension=xdebug.so"
            ;;
        -*) ;;
        *)
            TESTSUITE=
            ;;
        esac
    done
fi

# @todo detect if the user ahs passed in any code coverage options. if so, or with -c, enable xdebug options
#       which support code coverage

# Note: make sure we run the version of phpunit we installed, not the system one. See: https://github.com/sebastianbergmann/phpunit/issues/2014

php ${PHPOPTS} $(dirname $(dirname $(dirname ${BASH_SOURCE[0]})))/vendor/phpunit/phpunit/phpunit --stderr --colors ${VERBOSITY} ${COVERAGE} ${TESTSUITE} "$@"

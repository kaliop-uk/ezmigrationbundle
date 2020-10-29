#!/usr/bin/env bash

# Uses env vars: MYSQL_DATABASE, MYSQL_HOST, MYSQL_PASSWORD, MYSQL_USER

# @todo support a -v option

set -e

source $(dirname ${BASH_SOURCE[0]})/set-env-vars.sh

ROOT_DB_USER=root
ROOT_DB_PWD=
DB_HOST=

# @todo check if all required vars have a value

if [ "${TRAVIS}" != "true" ]; then
    DB_HOST="-h ${MYSQL_HOST}"
fi

mysql ${DB_HOST} -u${MYSQL_USER} -p${MYSQL_PASSWORD} ${MYSQL_DATABASE}

#!/usr/bin/env bash

EZ_VERSION=$1
DB=$2
INSTALL_TAGSBUNDLE=$3
ROOT_DB_USER=root
ROOT_DB_PWD=
# @todo use the same env vars used by the docker container, even when on Travis
EZ_DB_USER=ezp
EZ_DB_PWD=ezp
DB_HOST=

# @todo check if all required vars have a value

if [ "${TRAVIS}" != "true" ]; then
    ROOT_DB_PWD="-p${MYSQL_ROOT_PASSWORD}"
    DB_HOST='-h mysql'
fi

ROOT_DB_COMMAND="mysql ${DB_HOST} -u${ROOT_DB_USER} ${ROOT_DB_PWD}"
EZ_DB_COMMAND="mysql ${DB_HOST} -u${EZ_DB_USER} -p${EZ_DB_PWD} ${DB}"

# MySQL 5.7 defaults to strict mode, which is not good with ezpublish community kernel 2014.11.8
if [ "${EZ_VERSION}" = "ezpublish-community" -a "${TRAVIS}" = "true" ]; then
    # We want to only remove STRICT_TRANS_TABLES, really
    #mysql -u${DB_USER} ${DB_PWD} -e "SHOW VARIABLES LIKE 'sql_mode';"
    echo -e "\n[server]\nsql-mode='ONLY_FULL_GROUP_BY,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'\n" | sudo tee -a /etc/mysql/my.cnf
    sudo service mysql restart
fi

${ROOT_DB_COMMAND} -e "DROP DATABASE IF EXISTS ${DB};"
${ROOT_DB_COMMAND} -e "CREATE DATABASE ${DB}; GRANT ALL ON ${DB}.* TO ${EZ_DB_USER}@'*' IDENTIFIED BY '${EZ_DB_PWD}';"

# Load the database schema and data from sql files present in either the legacy stack or kernel
if [ "${EZ_VERSION}" = "ezpublish-community" ]; then
    ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-legacy/kernel/sql/mysql/kernel_schema.sql
    ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-legacy/kernel/sql/common/cleandata.sql
fi
if [ "${EZ_VERSION}" = "ezplatform" ]; then
    ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-kernel/data/mysql/schema.sql
    ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-kernel/data/cleandata.sql
fi
if [ "${EZ_VERSION}" = "ezplatform2" ]; then
    ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-kernel/data/mysql/schema.sql
    ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-kernel/data/mysql/cleandata.sql
fi

if [ "${INSTALL_TAGSBUNDLE}" = "1" ]; then
    if [ -f vendor/netgen/tagsbundle/Netgen/TagsBundle/Resources/sql/mysql/schema.sql ]; then
        ${EZ_DB_COMMAND} < vendor/netgen/tagsbundle/Netgen/TagsBundle/Resources/sql/mysql/schema.sql
    else
        if [ -f vendor/netgen/tagsbundle/bundle/Resources/sql/mysql/schema.sql ]; then
            ${EZ_DB_COMMAND} < vendor/netgen/tagsbundle/bundle/Resources/sql/mysql/schema.sql
        else
            if [ -f vendor/netgen/tagsbundle/Resources/sql/mysql/schema.sql ]; then
                ${EZ_DB_COMMAND} < vendor/netgen/tagsbundle/Resources/sql/mysql/schema.sql
            else
                echo "WARNING: should have loaded the Netgen TagsBundle db schema file but could not find it!"
            fi
        fi
    fi
fi

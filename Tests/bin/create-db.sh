#!/usr/bin/env bash

# Set up a pristine eZ database and accompanying user (mysql only)
# NB: drops both the db and the user if they are pre-existing!
#
# Uses env vars: EZ_VERSION, INSTALL_TAGSBUNDLE, TRAVIS, DB_EZ_DATABASE, DB_EZ_PASSWORD, DB_EZ_USER, DB_HOST, DB_ROOT_PASSWORD, DB_TYPE, DB_CHARSET

# @todo support a -v option
# @todo finish support for postgres

set -e

source $(dirname ${BASH_SOURCE[0]})/set-env-vars.sh

ROOT_DB_PWD=
DB_HOST_FLAG=

if [ -z "${DB_HOST}" ]; then
    DB_HOST=${DB_TYPE}
fi

echo "Creating the eZ database and user and loading it with default data..."

# @todo check if all required vars have a value

case "${DB_TYPE}" in
    mysql)
        ROOT_DB_USER=root
        if [ "${TRAVIS}" != "true" ]; then
            ROOT_DB_PWD="-p${DB_ROOT_PASSWORD}"
            DB_HOST_FLAG="-h ${DB_HOST}"
        fi
        ROOT_DB_COMMAND="mysql ${DB_HOST_FLAG} -u${ROOT_DB_USER} ${ROOT_DB_PWD}"
        EZ_DB_COMMAND="mysql ${DB_HOST_FLAG} -u${DB_EZ_USER} -p${DB_EZ_PASSWORD} ${DB_EZ_DATABASE}"
        ;;
    postgresql)
        ROOT_DB_USER=postgres
        # we rely on .pgpass for auth
        ROOT_DB_COMMAND="psql -h ${DB_HOST} -U ${ROOT_DB_USER}"
        EZ_DB_COMMAND="psql -h ${DB_HOST} -U ${DB_EZ_USER} -d ${DB_EZ_DATABASE}"
        ;;
    *)
        printf "\n\e[31mERROR: unknown db type ${DB_TYPE}\e[0m\n\n" >&2
        exit 1
esac

# MySQL 5.7 defaults to strict mode, which is not good with ezpublish community kernel 2014.11.8
# @todo besides testing for Travis, check as well for MYSQL_VERSION
if [ "${EZ_VERSION}" = "ezpublish-community" -a "${DB_TYPE}" = "mysql" -a "${TRAVIS}" = "true" ]; then
    # We want to only remove STRICT_TRANS_TABLES, really
    #mysql -u${DB_USER} ${DB_PWD} -e "SHOW VARIABLES LIKE 'sql_mode';"
    echo -e "\n[server]\nsql-mode='ONLY_FULL_GROUP_BY,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'\n" | sudo tee -a /etc/mysql/my.cnf
    sudo service mysql restart
fi

case "${DB_TYPE}" in
    mysql)
        if [ -z "${DB_CHARSET}" ]; then
            if [ "${EZ_VERSION}" = "ezpublish-community" ]; then
                # eZPublish schema has column indexes which are too long for Mysql in stock config when using 4 bytes per char...
                DB_CHARSET=utf8
            else
                DB_CHARSET=utf8mb4
            fi
        fi
        ${ROOT_DB_COMMAND} -e "DROP DATABASE IF EXISTS ${DB_EZ_DATABASE};"
        # @todo drop user only if it exists (easy on mysql 5.7 and later, not so much on 5.6...)
        ${ROOT_DB_COMMAND} -e "DROP USER '${DB_EZ_USER}'@'%';" 2>/dev/null || :
        ${ROOT_DB_COMMAND} -e "CREATE USER '${DB_EZ_USER}'@'%' IDENTIFIED BY '${DB_EZ_PASSWORD}';" 2>/dev/null
        ${ROOT_DB_COMMAND} -e "CREATE DATABASE ${DB_EZ_DATABASE} CHARACTER SET ${DB_CHARSET}; GRANT ALL PRIVILEGES ON ${DB_EZ_DATABASE}.* TO '${DB_EZ_USER}'@'%'"
        ;;
    postgresql)
        if [ -z "${DB_CHARSET}" ]; then
            DB_CHARSET=UTF8
        fi
        ${ROOT_DB_COMMAND} -c "DROP DATABASE IF EXISTS ${DB_EZ_DATABASE};"
        ${ROOT_DB_COMMAND} -c "DROP USER IF EXISTS ${DB_EZ_USER};"
        ${ROOT_DB_COMMAND} -c "CREATE USER ${DB_EZ_USER} WITH ENCRYPTED PASSWORD '${DB_EZ_PASSWORD}';"
        ${ROOT_DB_COMMAND} -c "CREATE DATABASE ${DB_EZ_DATABASE} WITH ENCODING '${DB_CHARSET}'"
        ${ROOT_DB_COMMAND} -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_EZ_DATABASE} to ${DB_EZ_USER};"
        ;;
esac

# Load the database schema and data from sql files present in either the legacy stack or kernel
if [ "${EZ_VERSION}" = "ezpublish-community" ]; then
    ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-legacy/kernel/sql/${DB_TYPE}/kernel_schema.sql
    ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-legacy/kernel/sql/common/cleandata.sql
elif [ "${EZ_VERSION}" = "ezplatform" -o "${EZ_VERSION}" = "ezplatform2" -o "${EZ_VERSION}" = "ezplatform3" ]; then
    # @todo for ezplatform3, use the appropriate script instead of looking for an SQL file
    case "${DB_TYPE}" in
        mysql)
            ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-kernel/data/${DB_TYPE}/schema.sql
            ;;
        postgresql)
            # @todo for ezplatform 1, it seems there is little support to build a posgtresql installation
            #       for ezplatform 2, we should use the schema creation sql from legacy, then run all dbupdate sqls
            #       up to the current ezplatform version but no more...
            echo "Creation of an eZPlatform 2 or 3 schema not supported yet for Postgresql" >&2
            exit 1
            ;;
    esac

    if [ -f vendor/ezsystems/ezpublish-kernel/data/${DB_TYPE}/cleandata.sql ]; then
        ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-kernel/data/${DB_TYPE}/cleandata.sql
    else
        ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-kernel/data/cleandata.sql
    fi

    # work around bug https://jira.ez.no/browse/EZP-31586: the db schema delivered in kernel 7.5.7 does not contain _all_ columns!
    [[ $(composer show | grep ezsystems/ezpublish-kernel | grep -F -q 7.5.7) ]] && ${EZ_DB_COMMAND} < vendor/ezsystems/ezpublish-kernel/data/update/${DB_TYPE}/dbupdate-7.5.4-to-7.5.5.sql
fi

# @todo do not automatically load the eztags schema, but let the test executor tell us which extra sql files to load
if [ -f vendor/netgen/tagsbundle/Netgen/TagsBundle/Resources/sql/${DB_TYPE}/schema.sql ]; then
    ${EZ_DB_COMMAND} < vendor/netgen/tagsbundle/Netgen/TagsBundle/Resources/sql/${DB_TYPE}/schema.sql
else
    if [ -f vendor/netgen/tagsbundle/bundle/Resources/sql/${DB_TYPE}/schema.sql ]; then
        ${EZ_DB_COMMAND} < vendor/netgen/tagsbundle/bundle/Resources/sql/${DB_TYPE}/schema.sql
    else
        if [ -f vendor/netgen/tagsbundle/Resources/sql/${DB_TYPE}/schema.sql ]; then
            ${EZ_DB_COMMAND} < vendor/netgen/tagsbundle/Resources/sql/${DB_TYPE}/schema.sql
        else
            :
        fi
    fi
fi

#!/usr/bin/env bash

EZ_VERSION=$1
DB=$2
INSTALL_TAGSBUNDLE=$3
DB_USER=$4
DB_PWD=$5

if [ "$DB_PWD" != "" ]; then
    DB_PWD="-p${DB_PWD}"
fi

mysql -u${DB_USER} ${DB_PWD} -e "DROP DATABASE IF EXISTS ${DB};"
mysql -u${DB_USER} ${DB_PWD} -e "CREATE DATABASE ${DB}; GRANT ALL ON ${DB}.* TO ezp@localhost IDENTIFIED BY 'ezp';"
# Create the database from sql files present in either the legacy stack or kernel
if [ "$EZ_VERSION" = "ezpublish-community" ]; then mysql -u${DB_USER} ${DB_PWD} ${DB} < vendor/ezsystems/ezpublish-legacy/kernel/sql/mysql/kernel_schema.sql; fi
if [ "$EZ_VERSION" = "ezpublish-community" ]; then mysql -u${DB_USER} ${DB_PWD} ${DB} < vendor/ezsystems/ezpublish-legacy/kernel/sql/common/cleandata.sql; fi
if [ "$EZ_VERSION" = "ezplatform" ]; then mysql -u${DB_USER} ${DB_PWD} ${DB} < vendor/ezsystems/ezpublish-kernel/data/mysql/schema.sql; fi
if [ "$EZ_VERSION" = "ezplatform" ]; then mysql -u${DB_USER} ${DB_PWD} ${DB} < vendor/ezsystems/ezpublish-kernel/data/cleandata.sql; fi

if [ "$INSTALL_TAGSBUNDLE" = "1" ]; then
    if [ -f vendor/netgen/tagsbundle/Netgen/TagsBundle/Resources/sql/mysql/schema.sql ]; then
        mysql -u${DB_USER} ${DB_PWD} ${DB} < vendor/netgen/tagsbundle/Netgen/TagsBundle/Resources/sql/mysql/schema.sql
    else
        if [ -f vendor/netgen/tagsbundle/bundle/Resources/sql/mysql/schema.sql ]; then
            mysql -u${DB_USER} ${DB_PWD} ${DB} < vendor/netgen/tagsbundle/bundle/Resources/sql/mysql/schema.sql
        else
            if [ -f vendor/netgen/tagsbundle/Resources/sql/mysql/schema.sql ]; then
                mysql -u${DB_USER} ${DB_PWD} ${DB} < vendor/netgen/tagsbundle/Resources/sql/mysql/schema.sql
            else
                echo "WARNING: should have loaded the Netgen TagsBundle db schema file but could not find it!"
            fi
        fi
    fi
fi

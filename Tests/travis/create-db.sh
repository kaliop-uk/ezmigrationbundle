#!/usr/bin/env bash

EZ_VERSION=$1
DB=$2
DB_USER=$3
DB_PWD=$4

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

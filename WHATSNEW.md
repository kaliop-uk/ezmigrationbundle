Version 2.0.beta-1
==================

This version is a complete restructuring of the codebase, and brings along with it a few breaking changes.

The main changes are:

* the database table used to store migrations has changed. It uses different columns, and a different name by default,
    to avoid conflicts with the previous table

* the bundle should now work on PostgreSQL, or any other database supported by Doctrine - it has only been tested on
    MySQL though :-)

* the default directories used to store migrations definitions has changed as well. This because the file format for
    the definitions files has change a little bit as well

* naming change: what was previously called a `version` is now called a `migration`

* the `generate` command takes an optional 2nd argument, t make it easier to create migration definition files with
    a meaningful name other than "placeholder"

* the `status` command displays much more information than before:

    - the date that migrations have been executed
    - the reason for migration failure
    - any differences between migrations definitions and the definitions used at the time the migrations were executed

    It also lists *all* migrations: both the ones available on disk as defintion files, and the ones stored in the
    database (previously if you deleted a migration definition, it would just not be listed any more)

* the `update` command has been renamed to `execute` (but the previous name will be kept as alias for a while)

* the `execute` command now prevents concurrent execution of the same migration, stores in the database the reason of
    failure of execution, warns of invalid migration definitions before execution, makes proper usage of database
    transactions and probably more 

* php migrations are now fully supported (they used to have naming problems)

* the validity of migration definition files is now checked before migration execution is attempted

* the console commands now give more detailed, helpful error messages

* it is much easier now to extend the bundle, as proper Dependency Injection is used everywhere, as well as tagged services


The change in database structure are handled automatically by the bundle - by way of a migration that you wll have to run!
For more details about the upgrade, read the [upgrade guide](doc/Upgrading/1.x_to_2.0.md)


Versions 1.4.1 to 1.4.6
=======================

Please have a look at the Github releases page: https://github.com/kaliop-uk/ezmigrationbundle/releases

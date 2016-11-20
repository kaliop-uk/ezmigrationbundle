Version 2.5
===========

* New: add support for creating and deleting Content Type Groups


Version 2.4.2
=============

* Fix: improve detection of failed migrations when using separate processes and the child process dies without traces


Version 2.4.1
=============

* Improve fix for issues #76 and #78


Version 2.4
===========

* New: it is now possible to create, update and delete Object States and Object State Groups

* Fix: (issue #78) when using transactions, do not report 'Migration can not be ended' if there is an error during the
    commit phase

* Fix: (issue #76) when using transactions on either ezplatform or ezpublish with workflows, migrations might fail
    when creating/editing contents that the anon user can not access

* Fix: throw correct exception when trying to access unset property in API/Value objects

* Fix: properly set the 'enabled' field when creating languages

* BC BREAK: for developers extending the bundle: the method `endMigration` in interface `StorageHandlerInterface` has
    acquired a new parameter.
    Also, the unused DefinitionHandlerInterface has been removed. 


Version 2.3
===========

* New: the 'migration' command learned a `--skip` option, to tag migrations as to be skipped 

* BC BREAK: for developers extending the bundle: the interface `StorageHandlerInterface` has acquired a new method


Version 2.2.1
=============

* Fix: when using the `--separate-process` option for the 'migrate' command allow the migration to last up to a day,
     and do not wait until it is finished to print its output


Version 2.2.0
=============

* New: the 'migrate' command learned a `--separate-process` option, to run each migration it its own separate
    php process. This should help when running many migrations in a single pass, and there are f.e. memory leaks.


Version 2.1.0
=============

* New: it is now possible to set a reference to the path of a created Content/Location.
    This allow to use it subsequently when assigning a role with subtree limitation

* Fix: the documentation describing usage of the 'match' keyword for updating/deleting contents and locations was
    incorrect 

* Fix: the documentation describing usage of the 'limitations' keyword for managing roles was incorrect

* Fix: Role creation would not work when using eZPlatform

* BC BREAK: the 'limitation' keyword used to describe role assignments has been replaced by 'limitations' 
    (it was documented using the plural form before)


Version 2.0.0
=============

This version is a complete restructuring of the codebase, and brings along with it a few breaking changes.

The main changes are:

* the database table used to store migrations has changed. It uses different columns, and a different name by default,
    to avoid conflicts with the previous table

* the bundle should now work on PostgreSQL, or any other database supported by Doctrine - it has only been tested on
    MySQL though :-)

* naming change: what was previously called a `version` is now called a `migration` (in the docs, error messages,
    source code, etc...)

* the `generate` command takes an optional 2nd argument, it make it easier to create migration definition files with
    a meaningful name other than "placeholder".
    The options it supports also changed and behave differently from before. 

* the `status` command displays much more information than before:

    - the date that migrations have been executed
    - the reason for migration failure
    - any differences between migrations definitions and the definitions used at the time the migrations were executed

    It also lists *all* migrations: both the ones available on disk as definition files, and the ones stored in the
    database (previously if you deleted a migration definition, it would just not be listed any more)

* the `update` command has been renamed to `migrate` (but the previous name will be kept as alias for a while).

* the `migrate` command now prevents concurrent execution of the same migration, stores in the database the reason of
    failure of execution, warns of invalid migration definitions before execution, makes proper usage of database
    transactions. It also has a new option to disable the wrapping of each migration in a database transaction and
    probably more 

* it is now possible to specify the language to be used for content creation, either in the yml definition file, or
    using a command-line option (the value set in yml file takes precedence)

* a new command `migration` is available. For the moment, it allows to delete existing migrations from the database
    table, making it possible to retry migrations which previously failed, as well as to manually add to the table
    migration definitions which are outside of the expected paths.

* php migrations are now fully supported (they used to have naming problems for the generated php classes)

* the validity of migration definition files is now checked before migration execution is attempted

* the console commands now give more detailed, helpful error messages. The same happens when migration yml is invalid

* it is much easier now to extend the bundle, as proper Dependency Injection is used everywhere, as well as tagged services

* the bundle is now tested on Travis, using eZPublish versions from 2014.3 to eZPlatform 1.4.0
    (for the moment the test suite is not 100% complete, more tests will come in the future)

* changes to the YML definition language:

    - a vastly improved way of identifying contents and locations to update or delete:
        contents/locations to update and delete can be identified via the `match` keyword;
        for adding locations, the `match` keyword can also be used to identify the target contents.
        In both cases, `match` allows to identify by object id, object remote id, location id,
        location remote id, parent location id, parent location remote id and content type identifier.

    - it is now possible, using the key `lang`, to specify the language to be used for content creation/update,
        contentType creation/update, user creation and user_group creation

    - it is now possible to create and delete languages. See the unit test for an example of the syntax

    - the remote_id key used when updating objects will not match the location remote_id any more (it used to try that
        if matching the object remote id failed). You can use the 'match' key instead

    - the remote_id of the main location of a content is not updated automatically any more when the remote_id
        of the content is. You can use a separate step in your migration for that 

    - the 'identifier' field used to identify content types can now be a reference

    - when specifying values for a field of type 'ezauthor', it is not necessary to have a sub-key named 'authors'
    
    - when creating/updating contents, values for more 'complex' field types are supported via usage of the
        fromHash method. F.e. the ezcountry field type is now supported.
        The details of the hash structure have to be looked up in docs or code for each field type

    - when updating a location, it is now possible to change its remote_id

    - references can now be set when creating new locations, for the location id or its remote id

    - references can now be set to the remote id when creating contents

    - references are now supported for setting the values to object relation and object relation list attributes

    - references can now be used when updating or deleting user groups to identify the group(s) to act upon

    - references can now be used in ids when updating or deleting users to identify the users(s) to act upon
    
    - when creating/updating users, it is possible to assign them to a single group
    
    - deprecated keys:
        * 'group' for user group delete operations, in favour of 'id'
        * 'object_id' and 'remote_id' for content update operations, in favour of 'match'
        * 'object_id' and 'remote_id' for location create operations, in favour of 'match'
        * 'authors' for fields of type ezauthor

The change in database structure are handled automatically by the bundle - by way of a migration that you wll have to run!
For more details about the upgrade, read the [upgrade guide](doc/Upgrading/1.x_to_2.0.md)


Versions 1.4.1 to 1.4.10
========================

Please have a look at the Github releases page: https://github.com/kaliop-uk/ezmigrationbundle/releases

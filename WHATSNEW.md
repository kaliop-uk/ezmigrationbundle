Version 3.6 (unreleased)
========================

* Added a new type of migration step: `migration_definition`. Only supported mode so far: `generate`.
    For the moment, it is really useful for unit testing, mostly


Version 3.5
===========

* New: allow the `generate` command to easily create a migration definition in a custom directory instead of a bundle

* New: allow the `position` field for attributes in ContentType `create` migrations.
    *NB* the algorithm used for the sorting of ContentType fields has changed compared to previous versions, for both
    creation and update:
    - mixing fields with a specified position and without it results in the fields without position to always go last
    - for consistent results, it is recommended to always either specify the position for all fields or to none 
    - the eZ4 Admin Interface does *not* display the actual field position, and shows 10,20,30 instead... In order to
         see the _real_ position that fields currently have it is recommended to generate a ContentType `create` migration

* New: better support for content fields of type `ezmedia`:
    - it is now possible to put the binary files next to the migration file, in a subfolder named 'media', similar to 
        what was already possible for ezimage and ezbinaryfile
    - the attributes to be used in the migration yml to define an ezmedia field are more consistent with the others
    - the path to the media binary file in generated migrations has become an absolute path

* New: it is now possible to update Content Type Groups

* New: when creating a Content Type Group, it is possible to set a custom creation date

* New: it is now possible to generate migrations for Sections, Object States, Object State Groups and Content Type Groups

* New: it is now possible to set references to many more attributes when creating/updating Contents:
    content_id, content_remote_id, always_available, content_type_id, content_type_identifier, current_version_no,
    location_id, main_language_code, modification_date, name, owner_id, path, publication_date, section_id

* New: it is now possible to set references to many more attributes when creating/updating Locations:
    location_id, location_remote_id, always_available, content_id, content_type_id, content_type_identifier,
    current_version_no, depth, is_hidden, main_language_code, main_location_id, main_language_code, modification_date, 
    name, owner_id, parent_location_id, path, position, priority, publication_date, section_id, sort_field, sort_order

* New: two new migration steps are available: `content/load` and `location/load`.
   Their purpose is to allow to set references to the attributes of the loaded objects. This way it is f.e. possible
   to copy the section from one content to another

        -
            type: content
            mode: load
            match:
                remote_id: firstcontent
            references:
                -
                    identifier: section_ref
                    attribute: section_id
        -
            type: content
            mode: update
            match:
                remote_id: secondcontent
            section: "reference:section_ref"

* New: it is now possible to manually create references, including both bulk loading from file and getting the value from
    configuration parameters (useful f.e. to manipulate different contents based on environment).
    It is alos possible to dump the resolved reference values for debugging puproses.
    Details in the dedicated [documentation](Resources/doc/DSL/ManageReference.yml)

* New: the `generate` command can be used to generate SQL migrations in YML file format

* New: SQL migrations in YML file format can set references to the number of affected rows

* New: the `migrate` command now accepts short-version for all options

*  New: an `assert` step is available to validate migrations used by the test suite

* Improved: make it easier to run the test suite outside of Travis and revamp test instructions

* Fix: content creation from the `generate` command would fail if a field of type Relation has no value 

* Fix: section updates would fail at setting the name

* Fix: 'match all' would fail for Object States

* Fix: properly resolve references in match conditions when these are specified using nested conditions with AND and ORs


Version 3.4
===========

* Added a new event: `ez_migration.migration_aborted` that can be listened to by user code, triggered when a
    `MigrationAbortedException` is thrown by a migration executor

* Fix BC with custom Complex FieldType handlers created by extending the bundle (bug introduced in 3.2) 


Version 3.3
===========

* Fixed: on content creation, assigning a section by identifier instead of id 

* New: allow setting section upon UserGroup creation and update

* New: when setting a value for a 'selection' content field, it is now possible to use the selection string value instead
    of its numerical index; it is also possible to use a single index/name instead of an array

* New: when using the `generate` command for content creation migrations, 2ndary locations are exported. Also, the sort
    field and sort order of the main location are exported

* New: it is now possible to use a specific type of exception during execution of a Migration Step (or a listener) to
    abort a migration without having it flagged as failed - it can be flagged as either done or skipped by throwing a
    `MigrationAbortedException`

* New: it is now possible to create/update/delete sections via migrations

* Improved many error messages


Version 3.2.2
=============

* Fixed: do not throw an exception when running the migration:migration --delete command and the migrations table is missing 


Version 3.2.1
=============

* Fixed: the 'lang' parameter was not taken into account by the `generate` command

* Improve docs: describe the settings available for contentType creation


Version 3.2.0
=============

* Allow setting a remote_id when creating/updating User Group(s)

* Allow matching on "content_remote_id" for User Group update/delete

* When updating users, allow to specify groups using references and remote_ids (it was already possible on creation)

* User group's "parent_group_id": if a string provided, it is considered referencing a user group's remote_id instead of
    its id

* It is now possible to match the entities to update/delete using composite conditions with `and` and `or`: 
    
        match:
            or:
                -
                    identifier: something
                -
                    and:
                        -
                            content_type: folder
                        -
                            parent_location_id: 42 

    NB: the match operations using composite conditions are not yet optimized for speed or memory usage!

* When updating/deleting Roles, Object States, Object State Groups, Content Types and Content Type Groups, it is now
    possible to match 'all' items. 
    
        match:
            all: ~

    It is also possible to match using negative predicates by using the following syntax:

        match:
            not:
                identifier: something
    
    Note: 'delete all' migrations will most likely not work as long as you have any remaining content...

    NB: it is not yet possible to match Content, Location or Tag using the `not` condition  

* Extend the `generate` Command to generate migrations for existing Contents and ContentTypes besides Roles;
    it is also possible to generate both _create_, _update_ and _delete_ migrations, and to have a single migration
    generated for many items.

    *NB* this feature is to be considered _experimental_, as there are some quirks in the generated migration files.
    In other words: not all migration files generated with the Generate command will work as is; some manual editing
    might be required before they are accepted as valid for execution.

    Known problems include, but are not limited to:
    - the field-settings generated for some field types when creating a ContentType migration might be invalid. Fe. in
        some eZPublish versions a field-setting `defaultLayout` for an ezpage field with a value of empty string will
        be generated but not be executable
    - when creating a ContentType migration, having a field of type ezuser set to 'searchable' will also cause the
         generated migration not to be executable
    - the export + reimport of content fields of type ezuser seems to be problematic
    - the export + reimport of content fields of type image and binaryfile has not been tested on eZPlatform

    Some of these problems originate within the eZPublish kernel, and are hard to work around in the bundle.
    For more details see: https://jira.ez.no/browse/EZP-26916


Version 3.1.0
=============

* Support 'default_always_available' in content type definitions

* Add new remote_id property and reference support for parent_tag_id property in tag creation

* Better compatibility with eZPlatform when matching Contents by Content Type


Version 3.0.3
=============

* Fixed: handling of relation-list attributes that are not references


Version 3.0.2
=============

* Improved: integer values used for content creation/modification dates are now assumed to be timestamps (the same as
    is done for datetime fields in contents)


Version 3.0.1
=============

* Fixed: creation of roles with a SiteAccess limitation

* Improved: reduce the chances of mysql deadlocks when running migrations in parallel


Version 3.0.0
=============

* New: it is now possible to store migration definitions in json format instead of yaml.
    The json format is not documented separately, as it is identical in structure to the yaml one. 

* New: the 'migrate' command learned to give information ion the executed steps when using the `-v` option

* New: it is now possible to set values to content fields of type eztags

* New: updating and deleting of Users, User Groups, Roles and Content Types now accepts more flexible definitions of
    the elements to act upon, and comprehensive resolution of references

* New: it is now possible to assign a section when creating and updating Contents

* New: it is now possible to assign an owner and a version creator when creating and updating Contents

* New: it is now possible to set publication and modification dates when creating and updating Contents

* New: it is now possible to assign object states when creating and updating Contents

* New: it is now possible to assign a remote id for the Location when creating Contents

* New: it is now possible to specify a file name and mime type when creating/updating content fields of type image and
    binary file

* New: references are now resolved for user_id and group_id when assigning Roles 

* New: the `parent_location` and `other_parent_locations` tags in Content creation, as well as `parent_location` in
    Location creation will now try to identify the desired Location by its remote id when a non integer value is used

* New: the 'roles' specified when creating user groups can be so via a reference

* New: the content_type_group tags in ContentType creation will accept a Content Type Group identifier besides an id

* Fix: when creating/updating contents, a NULL value is now accepted as valid by all (known) field types, including
    object relation, image and binary file

* Fix: migrations will not silently swallow any more errors when creating users or assigning roles and a non-existing
    group is specified

* New: made it easier to allow custom references types to be substituted in xmltext and richtext fields

* New: it is now possible to use a priority to the services tagged to act as complex field handlers
 
* New: added 2 events to which you can listen to implement custom logic just-before and just-after migration steps are
    executed:

    * ez_migration.before_execution => listeners receive a BeforeStepExecutionEvent event instance

    * ez_migration.step_executed => listeners receive a StepExecutedEvent event instance

* New: it is now possible to add resolvers for custom references using tagged services. The tag to use is: 
    `ez_migration_bundle.reference_resolver.customreference`. 
    For an example, see the test UnitTestOK010_customrefs.yml and class 
    Kaliop\eZMigrationBundle\Tests\helper\CustomReferenceResolver

*  New: it is now possible to inject field type handlers for scalar field types, as well as for field type handlers
     that only affect the field type of a single content type.
     This gives greater flexibility in deciding which types of references are resolved for each field when creating 
     or updating contents

* Changed: removed unused Behat and Phpunit tests

* Changed: removed from the YML documentation the description of keys which had been deprecated in version 2.
    The keys are still accepted, but support for them will be dropped in a future version

* Changed: the service `ez_migration_bundle.reference_resolver.content` is now deprecated and will be removed in a future
    version; the service `ez_migration_bundle.helper.role_handler` has been removed

* Changes to the YML definition language:

    * renamed the `main_Location` tag for content creation to `parent_location`. The old syntax works but is deprecated

    * renamed the `other_Locations` tag for content creation to `other_parent_locations`. The old syntax works but is deprecated

    * Creation and update of content: the format use to specify the attributes has been simplified. The old format is
        still working but is considered deprecated and will be removed in the future 

    * Updating and deleting of Users, User Groups, Roles and Content Types: usage of a `match` key is allowed; previous
        ways of defining elements to match are deprecated

    * new tags `modification_date`, `section`, `object_states` and `owner` are available for content creation and update

    * content creation supports the following new tags: `is_hidden`, `sort_field`, `sort_order`, `location_remote_id`

    * content update supports the following new tags: `creation_date`

    * location creation and update now support the tag `parent_location` as preferred form for `parent_location_id`.
        The latter variant is considered deprecated and will be desupported in a future version

    * rich text fields in content creation/update can be specified using a string instead of an array with key 'content'.
        References will still be resolved if found in the xml text

    * when specifying values for image and binary-file fields, the tags `filename` and `mime_type` can now be used

* BC BREAKS:

    * when deleting users, a single migration step can not be used any more to match users based at the same time on id,
        email and login. Use separate steps for that

    * when creating an object and defining its remote_id, the location remote_id is not automatically set any more.
        On the other hand, it is possible to set the location remote id explicitly using tag `location_remote_id`

    * when adding locations without defining sort field and sort order, the defaults from the content type definition
        are used, instead of publication-date/asc

    * references to 'tag:' and 'location:' will not be resolved any more in fields of type Object Relation and 
        Object Relation List. On the other hand non-integer strings will be resolved as remote-content-ids

    * changes for developers who extended the bundle: too many to be listed here. New interfaces where added, existing
        interfaces modified, etc... A lot of service definitions have been modified as well.


Version 2.5.1
=============

* Fix: bring back the support for resolving 'location:<remote_id>' tags as was done in version 1.X of the extension 

* New: better error messages on content creation/update when content fields do not validate (issue #84)


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

* php migrations are now fully supported (they used to have naming problems fpr the generated php classes)

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

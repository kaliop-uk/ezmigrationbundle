Version 5.15.0
==============

* New: it is now possible to dump all of a content's languages when generating `content/create` and `content/update`
  migrations. In order to do so, pass `--lang=all` on the command line

* Fixed: allow usage of shorthand notation when setting references in `file` migration steps

* Fixed: generating `content/create` and `content/update` migrations would fail with eZPlatform 1 and later for any
  contents with non-null ezbinaryfile and ezmedia fields

* Improved: reduced the amount of test infrastructure setup code by relying on an external tool: https://github.com/tanoconsulting/euts


Version 5.14.0
==============

* New: support for eZMatrix fieldType (issue #217).

* New: Content and Location matchers, used in `load`, `update` and `delete` steps for Content and Location can now match
  by QueryType (issue #239)

* New: migration step `user/create` can now assign roles to the newly created user (besides the roles automatically
  inherited from the user's groups) (issue #77)

* New: taught the `kaliop:migration:status` command to display full migration path by using the `--show-path` option
  (issue #152)

* Improved: when the `kaliop:migration:status` command is run with `--path`, it will now filter out according to the
  given paths not only the available migrations, but also the registered/executed/failed/suspended ones

* Improved: taught the test-execution command `teststack.sh` to generate code coverage reports, by running
  `teststack.sh runtests -- --coverage-html=/some-dir`. Note that it might take a long time to run

* Improved: allow to run unit tests on a PostgreSQL database instead of MySQL. At the moment this works correctly
  for testing against eZPublish Platform but not against eZPlatorm 1/2/3

* Deprecated: matching using keys: `contenttype_id`, `contenttypegroup_id`, `objectstate_id`, `objectstategroup_id`,
  `usergroup_id` has been deprecated in favour of `content_type_id`, `content_type_group_id`, `object_state_id`,
  `object_state_group_id`, `userg_roup_id`. The same applies for the equivalent `..._identifier` keys.
  This makes the DSL more consistent.


Version 5.13.0
==============

* Improved: a single value for a content field of type ezcountry can be specified as a string instead of an array (issue #190)

* New: taught the `kaliop:migration:status` command to sort migrations by execution date using `--sort-by` (issue #224)

* New: taught the `kaliop:migration:migrate` command a new option: `--set-reference` (issue #162). This is allows to
  inject any desired reference value into the migrations.

* New: taught the `kaliop:migration:resume` command the same new option: `--set-reference`

* Improved: references can now be set using a simplified syntax. eg:

          -
              type: content_type
              mode: load
              match:
                      identifier: philosophers_stone
              references:
                  my_ref_name: content_type_id

  Note that you still need to use the old syntax for reference creation in order to be able specify `overwrite: true`

* New: taught the `reference/set` migration step to resolve environment variables besides Symfony parameters (issue #199).
  Eg:

        -
            type: reference
            mode: set
            identifier: myReference
            value: '%env(PWD)%'

* New: taught the `reference/set` migration step _not_ to resolve environment variables at all, eg:

        -
            type: reference
            mode: set
            identifier: a_funny_string
            value: 'reference:or_not_to_reference'
            resolve_references: false

* New: taught the SQL migration step, when specified in yaml format, to resolve references embedded in the sql statement
  (issue #199), eg:

        -
            type: sql
            resolve_references: true
            mysql: "UPDATE emp SET job='sailor' WHERE ename='[reference:example_reference]'"

* New migration step: `sql/query`, which can be used to run SELECT queries on the database (issue #199).
  Unlike the existing `sql/exec` step (previously known simply as `sql`), this step allows to set reference values with
  the selected data. Ex:

          -
              type: sql
              mode: query
              mysql: "SELECT login FROM ezuser WHERE email like '%@ez.no'"
              expect: any
              references:
                  -
                      identifier: users_count
                      attribute: count
                  -   identifier: users_login
                      attribute: results.login

  For more details, see the complete specification in file SQL.yml

* New migration steps: `content_type_group/load` and `trash/load`

* New migration step: `migration/fail`, which is similar to `migration/cancel`, but leaves the migration marked as
  failed instead of executed

* New: migration step `proces/run` now supports element `fail_on_error`, which triggers a migration failure if the
  external process executed returns a non zero exit code (issue #234)

* New: all load/update/delete steps, as well as a couple non-repository-related steps, support the optional `expect` element.
  This is used to validate the number of matched items, as well as altering the value of the references created.
  - use `expect: one` to enforce matching of exactly one element, and set scalar values to references
  - use `expect: any` to allow steps matching of any number of elements, and set array values to references
  - use `expect: many` to enforce matching of one or more elements, and set array values to references
  - using `expect` enforces validation of the number of matched elements regardless of the fact that there are any reference
    definitions in the step, whereas `references_type` and `references_allow_empty` only activated if there was at least one
    reference defined
  - also, the validation of the number of matched elements, when required, now happens _before_ any item deletion/update
    action takes place. Up until now, for `update` steps, only a subset of the validation was enforced before the action,
    and the rest was validated afterwards

* Improved: using the `not` element in matching clauses would not work for most types of steps, when the element
  not-to-be-matched was not present in the repository. Notable exceptions being Content and Locations matches.
  This case now works.
  Example of a migration that would fail: find all content types except the one 'philosophers_stone'

          -
              type: content_type
              mode: load
              match:
                  not:
                      identifier: philosophers_stone

* New: most load/update/delete steps support the optional `match_tolerate_misses` element (issue #235).
  When setting it to true, the migration will not abort if there are no items in the repository matching the specified
  conditions.
  Example of a migration that would previously always fail: update non-existing content type 'philosophers_stone'

          -
              type: content_type
              mode: update
              match:
                      identifier: philosophers_stone
              # make this step successful in case we have not found the stone yet...
              match_tolerate_misses: true

* Fixed: setting references using jmespath syntax in migration steps `migration_definition/generate`

* BC change: when matching users by email in steps `user/update`, `user/delete`, `user/load` the migration will now
  be halted if there is no matching user found. This can be worked around by usage of `match_tolerate_misses: true`

* Improved: step `reference/dump` will not echo anything to stdout any more in case the `migrate` command is run with `-q`

* Improved: when generating migrations for Role creation/update, the bundle now tries harder to sort the Role Policies
  in a consistent way, which should make it easier to diff two Role definitions and spot changes

* Improved: made console command `kaliop:migration:migrate` survive the case of migrations registered in the database
  as 'to do' but without a definition file on disk anymore - a warning message is echoed before other migrations are run
  in this case

* Improved: made console command `kaliop:migration:migrate -vv` more verbose than `kaliop:migration:migrate -v`.
  Besides printing one message before each step begins execution, it also displays time taken and memory used for each
  step (issue #200).
  Also, improved the output of `kaliop:migration:migrate -v` by printing step numbers

* New: migration steps can now take advantage of `$context['output]` to echo debug/warning messages (issue #201).
  When set, it is set to an OutputInterface object.

* Improved: many new and improved test cases

* New: taught the test-execution command `teststack.sh` two new actions: `console` and `dbconsole`, as well as a few new
  options: `-r runtests`, `cleanup ez-cache` and `cleanup ez-logs`. It also accepts the name of a testcase to be run
  instead of the whole suite and other phpunit command line options, when executing `runtests`.

* Fixed: regressions when running the test-execution command `teststack.sh` with the `-u` option or `resetdb` action

* BC change: some options for the test-execution command `teststack.sh` have been renamed, see `teststack.sh -h`
  for the new list

* BC change: the `references_type` and `references_allow_empty` step elements have been replaced by a new element: `expect`.
  The `references_type` and `references_allow_empty` step elements are still handled correctly, but considered deprecated;
    equivalence matrix:
        `expect: one` <==> `references_type` not set or equal to `scalar`
        `expect: any` <==> `references_type: array` and `references_allow_empty: true`
        `expect: many` <==> `references_type: array` and `references_allow_empty` not set or equal to false
  For developers: the `RepositoryExecutor` class and its subclasses have dropped/changed methods that deal with setting
  references. You will have to adapt your code if you had subclassed any of them

* BC change: the database tables used by the bundle are now created by default with charset `utf8mb4` and collation
  `utf8mb4_general_ci` (issue #176). They used to default to utf8 and utf8_unicode_ci.
  This is in general not a big issue, as there are no queries with joins between our tables and the eZP ones, but in
  case you have custom code that does those queries, those might fail if the charset or collation differ.
  To fix that, you can set different values to the Symfony parameters `ez_migration_bundle.database_charset` and
  `ez_migration_bundle.database_collation`.
  Note that this 'change' only applies to new installations of the bundle - if the migration tables already existed
  in your database before upgrading to the latest Migration Bundle version, they will not be modified.

* BC change: some cases of \InvalidArgumentException being thrown have been replaced with Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException


Version 5.12.0
==============

* Improved: make the bundle compatible with PHP 7.4

* Improved: made it easier to run the test suite locally using multiple Docker stacks for different php/mysql versions


Version 5.11.0
==============

* New: new constraints `isnull` and `notnull` are now supported in 'if' clauses to match references values

* New: the `migration_definition/generate` step now supports an 'if' clause

* New: the `migration_definition/generate` step now supports setting a reference to the whole definition

* New migration step: `migration_definition/save`. Useful in content migrations / syndication scenarios

* BC changes:

  - the `migration_definition/generate` step now uses a different syntax for setting references. The old one is
    still accepted but deprecated (key `json_path` has been replaced by `attribute`)


Version 5.10.2
==============

* Fixed issue #232: error with EmbeddedRegexpReferenceResolverTrait.php and php 7.4

* Improved: massively reworked travis setup to make it friendlier to ezplatform 3 installations


Version 5.10.1
==============

* Fix issue #216: cannot update a location's parent matching it by remote id

* Improved: when creating/updating content, allow to set references to `location_remote_id`

* Improved: add plumbing to allow future usage of custom content types for UserGroups


Version 5.10.0
==============

* Fix issue #210: cannot match locations by group

* Fix: matching users by usergroup_id

* Fix: `file` migration steps would not work when using an `if` element

* Fix issue #207: java.lang.NegativeArraySizeException error when using SOLR multi core

* Improved the DSL docs for the management of Roles (see issue #211)

* Implemented request #205: allow to generate migrations for tag creation independently of content

* Implemented request #215: better error message when migrations fail because an invalid admin account is used to run them

* Implemented request #211: allow to unassign roles from groups on update

* Allow more flexibility in tag matching:
  - allow to match all tags
  - when specifying a parent-tag id, the remote_id can be used in its place

* Implemented request #204: an event of class MigrationGeneratedEvent is now emitted when a migration definition is
  generated via the command `kaliop:migration:generate`, allowing developers to easily customize the generated migrations

* Improved: it is now possible to set a reference to the `remote_id` of any created/updated/deleted userGroup

* Added a Docker-Compose based stack to ease execution of the test suite locally. See the main README for details on use


Version 5.9.5
=============

* Fix issue: usergroup_id matching for users was not working. Thanks @korsarNek


Version 5.9.4
=============

* Fix issue #202: RoleManager::createLimitation fails when using array reference


Version 5.9.3
=============

* Fix: match 'all' languages would raise an exception

* Improved: better error reporting by the `migrate` and `mass_migrate` commands.
    In particular, they now report the number of non-executed migrations besides the failed and skipped ones.

* Improved: better support for `-v` and `-q` options for the `migrate` and `mass_migrate` commands, esp. when used
    together with `-p`

* Improved: the `migrate` commands exits with non-0 exit code when any migration failed, even if it is given the `-i` option

* New: the `migrate` and `mass_migrate` commands accept an option `survive-disconnected-tty`. This helps in cases where
    you would normally run the migrations using `screen` or `tmux`, such as over ssh connections which risk being dropped
    before the migrations have finished executing

* Renamed option `force-sigchild-enabled` to `force-sigchild-enabled` and actually made it work (see notes for 5.9.1
    below for the explanation about its usage)

* BC changes:

   - code which relies on parsing the output and/or exit code of `migrate` and `mass_migrate` commands should be adjusted


Version 5.9.2 - please don't use
================================

* Fixed: when migrations fail, the error message is written to stderr instead of stdout, for both the `migrate` and
    `mass_migrate` commands

* Fixed: better error reporting when executing migrations as separate processes (using the `-p` option)

* Fixed: better error reporting by the `mass_migrate` commands:

    - return a non-0 exit code when at least one migration or one subprocess failed
    - report '0 or more' migrations failed when at least one subprocess failed (this was later changed in rev. 5.9.3)

* BC changes:

   - code which relies on parsing the output and/or exit code of `migrate` and `mass_migrate` commands should be adjusted


Version 5.9.1 - please don't use
================================

* New: the `migrate` and `mass_migrate` commands accept an option `force-sigchild-handling`.
    This is useful when you are running on eg. Debian and Ubuntu linux, and run the migrations using separate subprocesses:
    in such scenario there are chances that migrations will be reported as having failed executing even though they
    have not. Using the `force-sigchild-handling` option should fix that.
    For reference, see comment 12 in this ticket: https://bugs.launchpad.net/ubuntu/+source/php5/+bug/516061

    *NB* this option actually did not work as intended, and has been replaced in version 5.9.3


Version 5.9.0
=============

* New: the `role/create` migration step now resolves references for role names. Same for `role/update`.

* New: new migration steps `language\update`, `language\load`, `section\load`, `role\load`, `object_state\load`, `object_state_group\load`

* New: more flexible matching for migration step `language\delete`

* New: more reference resolving in section creation and update

* New: the `generate` command now has a `--list-types` option that will have it list all migration types available for
    generation

* Fix: warnings generated when creating array-valued refs using an empty collection of items

* Fix: references would not be resolved for Author and Selection fields, when the field value is given in array form.
    Ex: this will now be resolved

        ...
        attributes:
            country: # an ezselection field
                - italy
                - reference:mycountry

* BC changes:

    - the `language\delete` step should not be used any more with a `lang` element, but with `match` instead


Version 5.8.0
=============

* New: the `content_type/update` migration step now accepts the `default_always_available` element (issue #189)

* New: the `kaliop:migration:generate` command now accepts a `-a` flag to allow specifying custom admin users (issue #187)

* Fix: usage of the `-a` flag when running `kaliop:migration:mass_migrate` and when running `kaliop:migration:migrate -p`
    was not propagated to subprocesses

* Fix: the `if` element was not giving a fatal error for all migration steps affecting repository elements (Content,
    Location, etc...), at least for Symfony version 2.7.10

* New: the command `kaliop:migration:migrate` now accepts a `--force` flag that will execute
    migrations that were previously executed or skipped or failed.
    *NB* this flag is useful when testing migrations, but should be used sparingly in production context, as replaying
    migrations that had already been executed can wreak havoc to your database. *you have been warned*

* BC changes:

    - the `kaliop:migration:generate` command now uses as default language for the generated migrations the default one
      of the current siteaccess, instead of 'eng-GB'


Version 5.7.3
=============

* Fix: warnings due to ContentVersionMatcher methods signatures

* Fix: creating migrations for Content creation or update with contents which have empty Image/File/Media fields would
    crash


Version 5.7.2
=============

* An attempt at fixing php warnings that turned out to be wrong


Version 5.7.1
=============

* An attempt at fixing php warnings that turned out to be wrong


Version 5.7.0
=============

* New: when manipulating Locations, it is now possible to set references to `content_remote_id`

* Fix: the migrations generated for `content/create` and `content/update` were missing information about section and
    object states

* New: new migration step: `content_version/load`. Example:

        -
            type: content_version
            mode: load
            match:
                content_id: 2893941
            match_versions:
                status: archived
            references_type: array
            references:
                -
                    identifier: archived_versions_for_2893941
                    attribute: version_no

* New: migration step: `content_version/delete` can now match the versions based on status, as well as using
    complex conditions based on and/or/not

* New: when manipulating content versions, it is now possible to set references to `version_no` and `version_status`

* BC changes:

    - migration step: `content_version/delete` has deprecated the element 'versions' in favour of 'match_versions'

    - when manipulating content versions, it is not possible any more to set references to attributes (might be fixed in the future)


Version 5.6.0
=============

* New: when using step `reference/dump`, it is possible to use a custom label instead of the reference name

* New: when creating array references, it is possible to allow them to be empty without this being considered an error.
    Ex:

                ...
                references_type: array
                references_allow_empty: true
                references:
                    -   identifier: my_content_ids
                        attribute: content_id

* New: the classes which implement `MigrationGeneratorInterface` will now receive the whole step definition as part
    of the `$context` parameter for the `generateMigration` call. This allows them to tailor the generated migration
    definition based on custom conditions.


Version 5.5.1
=============

* New: when updating, deleting, loading Contents and Locations, you can now sort the results, as well as use
   an offset and limit. Ex:

        -
            type: content
            mode: load
            match: { content_type_identifier: article }
            match_offset: 0
            match_limit: 10
            match_sort:
                - { sort_field: published, sort_order: desc }

* New: it is now possible to generate migration definition files via migration steps.
    Also, more references are resolved in migrationdefinition/generate steps.

* New: it is now possible to set the location remote_id when creating locations
    Also, more references are resolved in location/create and location/update steps.

* New: it is now possible to match users using their group id

* New: it is now possible to match user groups using the parent group id

* BC changes:

    - classes ContentMatcher and LocationMatcher now implement a different interface. If you have subclassed them, you
        will need to adjust the definition of methods `Math` and `MatchOne`

    - when migrations are generated that specify a Location, element `content_id` is now used where `contentobject_id` was
        beforehand for indicating a sort order, and `location_id` is used where `node_id` was


Version 5.5.0
=============

Accidental release ;-)


Version 5.4.1
=============

* Fix: when a sub-migration is skipped in a step of a loop, do not halt the loop immediately but go on un til the end

* Fix: references are now resolved for the "over" element of loops


Version 5.4
===========

* Fix: changing ContentTypeGroup upon updating ContentType

* Fix: setting custom modification date on Content Create (ticket #173)

* New: it is now possible to create or update a Content setting multiple translations at the same time. Ex:

        -
            type: content
            mode: create
            content_type: myClass
            parent_location: 2
            attributes:
                title:
                    eng-GB: hello world in eng-GB
                    abc-DE: hello world in abc-DE

    *Note* that in order for the content definition to be considered valid "multi-langauge", ALL attributes values must
    be specified using a language key. The following example is thus _invalid_:

            attributes:
                title:
                    eng-GB: hello world in eng-GB
                    abc-DE: hello world in abc-DE
                description: A description to rule them and in the drakness bind them

* New: it is now possible to use Symfony Expression language in IF conditions. Ex:

        -
            type: ...
            if:
                "reference:some_id":
                    satisfies: "value % 3 == 0"

    Here  the migration step would only be executed if the Id stored in the reference is divisible by 3.

* New: it is now possible to loop over arrays, achieving the same as a php `foreach` call. Ex:

        -
            type: loop
            over: { "hello": world, "buongiorno": mondo }
            steps:
                -
                    type: reference
                    mode: set
                    identifier: loopref
                    value: "We have found key: [loop:key] and value: [loop:value]"
                    overwrite: true

    This should be useful f.e. in conjunction with references of type array, introduced in version 5.1


Version 5.3
===========

* Fix: declare incompatibility with Simfony 3.4.12

* Fix: declare compatibility with nikic/php-parser 4

* Fix: references set to locations attributes in 'location/update' steps that resulted in a location being moved or swapped
    would be wrong for path-related data

* New: it is now possible to use the `lang` key for filtering when matching contents

* New: it is now possible to alter the Groups that a ContentType belongs to in `content_type/update` steps

* New migration steps: `user/load` and `user_group/load`, which can be useful to set references

* New: it is now possible to set references to the users of a group and to the groups of a user

* New: it is now possible to set references to the parent of a user group

* New: it is now possible to set references to the groups of a content type


Version 5.2
===========

* New: references are resolved in all fields when creating/updating users. This makes it easier to mass-create users in
    conjunction with the eZLoremIpsum bundle

*  New: the `status` command got a `todo` option. When using it, all that is printed is the list of the migrations to
    execute (full path to each file). This can be useful fe. in shell scripts that want to execute each migration
    separately

* Fix: one case where array values where tried to be resolved as references (introduced in 5.1)

* Fix: it was impossible to import a content exported via migration generation with an eZPlatform Rich-Text field (ticket #160)

* Fix: better error reporting for the case where an error happens at the end of a migration (commit phase)


Version 5.1.1
=============

* Fix: a bug introduced in 5.1 with resolving references in handling tags

* Fix: do allow resolution of references for multi-valued Content Fields (eg. a reference that returns an array is ok
    to use for a field of type object-relation)


Version 5.1
===========

* New: it is now possible to set references when a migration step results in a list of items, and not just in a single
     item. The resulting reference will have a value which is an array instead of a scalar value.
     This has to be specifically enabled for each migration step where references are expected to be multi-valued:

        references_type: array
        references:
            -
                attribute: some_id
                identifier: my_array_ref

* New: references are now resolved for in the `keyword` element for `tag/create` and `tag/update`. This makes it
    easier to mass-create eztag tags in conjunction with the eZLoremIpsum bundle

* New: tags can now be matched by their parent tag id

* New: its is now possible to set references to a tag `keyword`

* New: it is easier to create/update tags in a single language (the main language of current siteaccess is used if unspecified)

* BC changes:

    - the RepositoryExecutor class now expects subclasses to implement method `getReferencesValues` and not `setReferences`
        any more


Version 5.0
===========

* New: everywhere references are accepted, text strings can now be used as well, which embed the reference within square
    brackets. This will lead to the substitution of the text within brackets with the value of the reference.

    Example: assuming that the 'myref' reference has a value of 99

    Possible before:

    ```
    match:
        content_id: "reference:myref"
    # we get content_id = 99
     ```

    Possible now:

    ```
    match:
        remote_content_id: "something [reference:myref] times different"
    # we get remote_content_id = "something 99 times different"
     ```

* New: it is now possible to create references with values that are generated from other references, such as:

    ```
    type: reference
    mode: set
    identifier: three
    value: "both [reference:one] and [reference:two]"

    ```

* New: when creating and updating ContentType definitions, it is possible to use a reference to define each field. The
    value of the reference must be an array with all the usual keys: 'name', 'searchable', etc...
    This makes it easy to define multiple ContentTypes sharing fields with the same definition such as f.e. the 'title'

    ```
    attributes:
        title: "reference:title_field_definition"
        body:
            name: Body
            ...
     ```

* New: added migration step `loop`. More details in [Resources/doc/DSL/Loops.yml](Resources/doc/DSL/Loops.yml)

* Changed: when no language is specified on the command line or in a specific migration step dsl, instead of defaulting to
    `eng-GB` we will default to the first language in the list of languages set up for the current siteaccess (this is
    usually found in the `ezpublish.yml` config file)

* New: the bundle is now tested on Travis with eZPlatform 2 besides eZPlatform 1 and eZPublish 5

* BC changes:

    - two new interfaces have been added: EmbeddedReferenceResolverBagInterface, EmbeddedReferenceResolverInterface.
      Those replace the expected types in the constructors of most Executors. If you have subclassed Executors, be
      prepared for some porting work


Version 4.7
===========

* New: migration step `file/prepend`, works just like `file/append` but adds content at beginning instead of end of file

* New: migration steps `file/append`, `file/prepend` and `file/save` can load the file contents from a template file on
    disk besides having it inline in the migration definition

* New: all migration steps that deal with the content repository, as well as sql, php class, sf services, files, mail,
    http calls, process execution have gained support for being skipped via an `if` element. The semantics are the same
    as for the existing step `migration/cancel`:

    ```
    if: # Optional. If set, the migration step will be skipped when the condition is matched
        "reference:_ref_name": # name of a reference to be used for the test
            _operator_: value # allowed operators: eq, gt, gte, lt, lte, ne, count, length, regexp
    ```

* New: it is now possible to define the following parameters using siteaccess-aware configuration:

    `kaliop_bundle_migration.version_directory`, `ez_migration_bundle.table_name`, `ez_migration_bundle.context_table_name`

    This is useful when you have multi-site eZPlatform installations which do not share a single Repository database, and
    as such might need to execute different sets of migrations for each site.

    As an example, just use this parameter format: `kaliop_bundle_migration.my_siteaccess_group.version_directory`


Version 4.6
===========

* New: allow resolving references for the `identifier` element of steps `create` and `update` for ContentType,
    ContentTypeGroup, ObjectState, ObjectStateGroup and Section


Version 4.5
===========

* New: allow resolving references for the `filename` element of steps `reference/load` and `reference/save`

* New: migration step `service/call` allows to call a method on any existing Symfony Service, and set a reference to the
    result


Version 4.4
===========

* Fixed: make the cli commands compatible with Symfony 3.0 and later

* New: the element `remove_drafts` can be used for migration steps of type ContentType/update to make sure that any
    existing drafts of the given ContentType are removed

* New: support the value '*' for the `remove_attributes` parameter in ContentType definitions. This allows to remove all
    the attributes which already exist in the ContentType, except for the ones defined in the `attributes` parameter

* New: added a new loader class to allow scanning the Migrations folders recursively for migrations files. Useful when
    you have a massive number of migration files and keeping them in a single folder hits the filesystem limits.
    At the moment, the only way to enable this is to redefine the alias in your app configuration, ie:

            ez_migration_bundle.loader:
                alias: ez_migration_bundle.loader.filesystem_recursive

* New: a new command `kaliop:migration:mass_migrate` is available to execute the migrations found in a directory, including
     all its subdirs, using a specified number of parallel processes.
     This is a somewhat efficient way to achieve f.e. mass import of contents via migrations

* Improved: when using the `separate-process` option to the `migrate` command, pass on to the child process the
    `no-debug` and `siteacess` options, if they have been specified by the user

* Improved: better error message when trying to generate a migration for the creation of a Role which has Policies with
    limitations that can not be exported


Version 4.3
===========

* Improved: do not use the Repository Search Service when matching Contents or Locations using only their Id or Location Id.
    This has the advantage that those items will always be loaded from the database, even in the case of Solr Search
    Engine Bundle being enabled, which in turn means that you should get fewer problems related to usage of database
    transactions and delays Solr in indexation.
    This fixes issue #134.
    *BC note*: if you use Solr Search Engine Bundle and the find the new behaviour undesireable, you can easily switch
    back to the previous one by altering the value for parameters `ez_migration_bundle.content_matcher.class` and
    `ez_migration_bundle.location_matcher.class`

* New: migration step: `migration/sleep` to delay execution of a migration for a fixed number of seconds

* New: migration steps: `location/trash`, `trash/purge`, `trash/recover` and `trash/delete`


Version 4.2
===========

* New: references are now resolved in `validator-configuration` settings for content type definitions. This is useful
    eg. when using the eZTags v3 bundle

* New: allow to set content main location


Version 4.1.1
=============

* Fix: allow usage with Solr as default search engine for migration steps matching contents or locations

* Fix: allow usage of phpunit 6 for migrations using 'assert' migration steps

* Improved: do not ask the search engine for the total number of hits for migration steps matching contents or locations.
    This should result in a minor speed improvement


Version 4.1
===========

* New: allow to set the 'name' and 'description' in many languages in a single 'create' or 'update' operation for ContentType.
    This is possible both for the ContentType itself as well as for all its attributes

* Fix: when updating a ContentType defined in many languages, do not loose 'name' and 'description' set in other languages

* New: allow to set references to the 'name' and 'description' of a ContentType

* New: it is now possible to remove specific versions of contents

* New: allow to set references to the 'version_count' of a Content

* New: allow to set references to the properties of a Tag: always_available, depth, main_language_code, main_tag_id, modification_date, path, parent_tag_id, remote_id

* New: operation 'load' 'tag' can be used to set references to existing tags properties

* New: operation 'update' 'tag' is supported

* Improved: better validation of the definition of fields of type eZSelection for ContentType creation/update


Version 4.0
===========

* New: allow to set references to contentType default_always_available, default_sort_field, default_sort_order, is_container

* Improved: parameter resolving in migration steps reference/set now works even when the parameter is not the full value of the string, eg: '%kernel.root_dir%/spool'

* Improved: path to attachment files can be specified relative to the migration file for migration steps mail/send


Version 4.0 RC-5
================

* New: allow to set default sort field/order on content type creation and update

* Fix: allow setting references to the number of items affected for update and delete steps


Version 4.0 RC-4
================

* New: added 'append' action to 'file' executor

* New: it is now possible to match contents based on what other contents they relate to/from

* New: it is now possible to set a reference to a Content State by using a syntax similar to f.e. `object_state.ez_lock` to
    specify the desired State Group

* New: allow to use a reference for Migration/Suspend when comparing to a date

* New: allow to set references to the number of items matched whenever updating/deleting any entity from the repository
    (contents, locations, etc...)

* New: allow to set references when deleting any entity from the repository

* New: allow to install along newer with Nikic/php-parser

* Fix the Migration/Cancel step

* Fix: retrieving body of http responses


Version 4.0 RC-3
================

* Improved: do not publish a new content version when only metadata is changed but not field values for content/update

* Fix: allow updating in one migration step many contents of different types

* New: allow to run migrations without changing into an admin user (only for developers, not yet from the command line)

* New: it is now possible to execute external processes as migration steps.
    More details in [Resources/doc/DSL/Processes.yml](Resources/doc/DSL/Processes.yml)


Version 4.0 RC-2
================

* New: it is now possible to send emails as migration steps.
    More details in [Resources/doc/DSL/Mails.yml](Resources/doc/DSL/Mails.yml)

* New: it is now possible to execute http calls as migration steps.
    More details in [Resources/doc/DSL/HTTP.yml](Resources/doc/DSL/HTTP.yml)

* New: it is now possible to load/save/delete/copy/move files as migration steps.
    More details in [Resources/doc/DSL/Files.yml](Resources/doc/DSL/Files.yml)


Version 4.0 RC-1
================

* New: the `migrate` command by default will print out the number of executed, failed and skipped migrations, as well as
    time and memory taken

* New: the `ka:mi:migration` command learned a new `--info` action to give detailed information on a single migration
    migration at a time

* New: the `ka:mi:status` command learned a new `--summary` option to print only the number of migrations per status

* New: migrations can now be cancelled by using a custom migration step. Ex:

        -
            type: migration
            mode: cancel
            if: ...

    More details in [Resources/doc/DSL/Migrations.yml](Resources/doc/DSL/Migrations.yml)

* New: migrations can now be suspended and resumed:

        -
            type: migration
            mode: suspended
            until: ...

    More details in [Resources/doc/DSL/Migrations.yml](Resources/doc/DSL/Migrations.yml)

* New: it is possible to use `overwrite: true` to change the value of an existing reference

* New: it is now possible to save the current references to a file

* New: it is now possible to specify a custom Content Type for users created via `user/create` migrations

* New: it is now possible to specify a custom Admin account used to carry out migrations instead of the user 14

* New: it is possible to use a 'not', 'attribute', 'creation_date', 'group', 'modification_date', 'object_state', 'owner',
    'section', 'subtree' and 'visibility' condition when matching Contents.
    Matching when using 'and' and 'or' is also more efficient

* New: it is possible to use a 'not', 'attribute', 'content_type_id', 'content_type_identifier', 'creation_date', 'depth',
    'group', 'modification_date', 'object_state', 'owner', 'priority', 'section', 'subtree' and 'visibility' condition
    when matching Locations.
    Matching when using 'and' and 'or' is also more efficient

* New: it is now possible to set references to the values of Content Type field definitions. The syntax to use is similar
    to the one available for Content fields, described in the notes for release 3.6 a few lines below

* New: it is now possible to set references to 'section_identifier' when creating/updating/loading Contents and Locations

* Fixed: removed from the list of possible references which can be set for Locations the non-working 'position'

* New: the Executor services have been made reentrant

* BC changes:

    - eZPublish 5.3 and eZPublish Community 2014.3 are not supported any more (eZPublish 5.3 ended support in May 2017)

    - the code will start targeting php 5.6 as minimum version starting with this release

    - the following interfaces have been modified: MigrationGeneratorInterface, StorageHandlerInterface,

    - the following deprecated interfaces have been removed: ComplexFieldInterface

    - lots of refactoring in the Core (non API) classes. If you have extended them, be prepared for some porting work


Version 3.6.1
=============

* Fixed: when setting both content creation and modification time upon content creation, modification time was lost


Version 3.6
===========

* New: it is now possible to set references to:
    - content type: creation_date, modification_date, name_pattern, remote_id, status and url_name_pattern
    - language: enabled, language_code, name
    - object state: priority
    - user: email, enabled, login

* New: it is now possible to set references to the values of Content fields. The syntax to use is slightly different
    depending on whether the value for the field at hand is a scalar or a hash. Example code:

            references:
                -
                    identifier: a_user_first_name_field_is_a_string
                    attribute: 'attributes.first_name'
                -
                    identifier: a_user_account_field_is_a_hash
                    attribute: 'attributes.user_account.email'

    TIP: if you are unsure about the hash representation of a given field, you can generate a content/create migration
    for an existing content to find out how it looks.

* Added a new type of migration step: `migration_definition`. Only supported mode so far: `generate`.
    For the moment, it is mostly useful for unit testing

* Fix a case of circular dependencies in services (highlighted by the workflow bundle)

* Fix: when setting a specific language via a 'lang' attribute in a migration step, the same language would be used for
    all subsequent steps in the same migration


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
    Details in the dedicated [documentation](Resources/doc/DSL/References.yml)

* New: the `generate` command can be used to generate SQL migrations in YML file format

* New: SQL migrations in YML file format can set references to the number of affected rows

* New: the `migrate` command now accepts short-version for all options

* New: an `assert` step is available to validate migrations used by the test suite

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
For more details about the upgrade, read the [upgrade guide](Resources/doc/Upgrading/1.x_to_2.0.md)


Versions 1.4.1 to 1.4.10
========================

Please have a look at the Github releases page: https://github.com/kaliop-uk/ezmigrationbundle/releases

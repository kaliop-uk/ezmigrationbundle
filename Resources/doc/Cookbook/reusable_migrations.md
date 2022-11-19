It is often required to execute the same migration logic multiple times, applying it each time to a different set of
contents. Example scenarios could be moving a content subtree to a given location, adding a value to an eztags field,
or anything else, really.

This is often done via copy-pasting-then-modifying the same migration steps into multiple migration definitions. If the
migration logic to apply is quite complex, this can result in a tedious and brittle process, as it is easy to forget to
change a reference or other parameter.

Since version 6.3, it is possible to achieve the same result in an easier way, with less chances for manual error.
The process is:

1. create the "core" migration definition, save it in separate folder, eg. a subfolder of the `MigrationsVersions` one.
   The core migration should be made flexible by being driven by references - which it does use without creating them 1st.

2. create one or more "single-execution" migrations which set up the required references and then include the core
   migration definition

A very simple example:

core migration: saved in `Migrationversions/library/addScreenshot.yml`

    -
        type: content
        mode: update
        match:
            content_id: reference:content_to_update
        attributes:
            screenshot:
                path: "/tmp/test.jpg"

migration to execute: saved as `Migrationversions/yyymmddhhmmss.yml`

    -
        type: reference
        mode: set
        identifier: content_to_update
        value: 1234
    -
        type: migration_definition
        mode: include
        file: library/addScreenshot.yml

Please do not that there is a downside of this approach, which is: once the core migration logic is saved in a dedicated
"library" of reusable migration definitions, normally kept in version control, it is tempting for the developer to
extend, improve or modify those reusable blocks. When this happens, it means that it will not be possible anymore in
the future to replay the same migrations which were executed in the past, to bring the database from state A to state B.
This kind of defeats the purpose of the migration bundle, which is to make sure that the same database state can
always be reproduced, going from old versions to the current one... (of course the same is true whenever a migration is
executed which relies on any external resource, such as making a call to a web service or loading data from a file,
or even setting a reference value from a command-line option)

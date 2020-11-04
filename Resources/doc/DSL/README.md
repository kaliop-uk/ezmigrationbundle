About the Migration Domain Specific Language (DSL)
==================================================

This directory contains the definitions of every supported action in the migration DSL, in the form of a commented Yaml file.

Specific topics are covered below.

*NB* For more examples of valid Yaml files describing migrations, have a look in the directory Tests/dsl/good


## Content language

By default, the bundle uses the first language in the list of languages set up for the current siteaccess for creating
all multilingual entities (contents, contentTypes, users, etc...).
In order to create content in a different language, either specify it in your yml definition files (recommended), or
use a command-line switch.


## Notes on field types

### Importing binary files

Below is an example snippet for creation/update of a content with a field of type ezimage:

    attributes:
        image:
            path: /path/to/the/image.jpg
            alt_text: 'Example alt text'

A simplified form, which does not allow for setting the 'alt' attribute, is:

    attributes:
        image: /path/to/the/image.jpg


The paths to files/images/media in the definition are either
* absolute paths, or
* relative paths from the MigrationVersions/images, MigrationVersions/files or MigrationVersions/media folders.

For example using the path from the snippet above the system would look first for the image file in
`MigrationVersions/images/path/to/the/image.jpg` in the bundle's directory, and if no file is found there, look for
`/path/to/the/image.jpg`

Please see the `Contents.yml` DSL definition file in the `Resources/doc/DSL` folder for more information.

### Importing ezflow zones and blocks

At the moment, the creation of blocks via the eZPublish 5 content API is not supported, and the migration bundle does
have code to work around that.
You will be able to create content with a field of type eZPage, but not add or edits its blocks.


## Using references in your migration files

The Yaml definitions support setting 'references' to the results of the execution of a migration step, that can be retrieved
and used in the subsequent migration steps.

Setting a reference is the same as creating a new variable - you decide its name, and which value it will hold (the value
is taken from the entity currently being created / updated / deleted / loaded). Once the reference is set, you can make
use of its value in other steps.

For example, you could set a reference to the location id of a folder that you create and then use that as the parent
location for creating articles in that folder.

Here is an example on using references:
the first step creates a new content type in the system and sets a reference to its id;
the second step adds a new policy to the editor role to allow editors to create objects of the new content type under
the location with id 2.

    -
        mode: create
        type: content_type
        content_type_group: 1
        name: Section Page
        identifier: section_page
        name_pattern: <title>
        is_container: true
        attributes:
            -
                type: ezstring
                name: Title
                identifier: title
                required: true
        references:
            -
                identifier: section_page_class
                attribute: content_type_id
    -
        mode: update
        type: role
        name: Editor
        policies:
            add:
                -
                    module: content
                    function: create
                    limitation:
                        -
                            type: Node
                            value: [2]
                        -
                            type: Class
                            value: [ "reference:section_page_class" ]

To set the reference we use the `references` section of the content type DSL. We set a reference named
`section_page_class` to store the content type id.
In the update role action we retrieve the value of the reference by using the `reference:section_page_class`.

To tell the system that you want to use a previously stored reference you need to prefix the reference name with the string
`reference:`. This instructs the system to look in the list of stored references and replace the current tag with the value
associated to the reference found. Eg:

> **Important:** Please note that the reference **must be a quoted string**, as `reference:<reference_name>` uses
> YAML reserved characters.
>
> **Bad:** `some_key: reference:foo`<br>
> **Good:** `some_key: 'reference:foo'`

Note that, unlike variables in programming languages, you can not change the value of an existing reference by default.
This is done to prevent accidental overwrites of an existing reference with another one, as the most common use case
for reference is set once, use multiple times.
If you want to be able to change the value of a reference after having created it, use the `overwrite` tag:

        references:
            -
                identifier: section_page_class
                attribute: content_type_id
                overwrite: true

*NB:* references are stored *in memory* only and will not propagate across different migrations, unless you
execute the migrations in a single command (and without the 'separate processes' switch).

*NB:* please do not use the character `]` in your reference names. See below for the reason.

### References in the XML for the eZXMLText Field

To tell the system to look for references in the xml that is used to populate ezxmltext type fields the Yaml definition
will need to use the definition used for defining complex attributes.
Please see the importing binary files section above on how to define complex data type handling for an attribute.

Below is an example snippet showing how to define references for ezxmltext.

    attributes:
        - description:
            type: ezxmltext
            content: '<section><paragraph><embed view="embed" size="medium" object_id="[reference:test_image]" /></paragraph></section>'

*NB:* when using references in xml texts you must include the two extra characters `[` and `]`, which are not needed
when using them as part of other elements in the yml file.
This is done to minimize the chances that some random bits of text get modified by error (and because we need an
end-of-reference identifier character).

### Scalar vs array references

By default the migration engine expect reference values to be scalars. This means that the following migration will fail:

    # FAIL
    -
        mode: load
        type: content_type
        match:
            not:
                content_type_identifier: whatever
        references:
            -
                identifier: my_ref
                attribute: content_type_identifier

(every eZ installation has by least one ContentType for users and one for UserGroups, generally more)

In order to be able to create references whose values are arrays, you have to explictly say so in the migration step

    # FAIL
    -
        mode: load
        type: content_type
        match:
            not:
                content_type_identifier: whatever
        expect: any
        references:
            -
                identifier: my_ref
                attribute: content_type_identifier

Valid values for `expect` also include positive numbers and the string `many`, used to indicate 'one or more'.
When those are used, besides having an effect on the type of values set to references, the number of items matched
is also validated.

### Complete list of available references

Look in the single DSL files for the complete set of attributes which can be used to set reference values, as well as
the places where references will be substituted if found.

### Setting references manually

It is possible to create references manually using a dedicated migration step, and even bulk-load them
from a file. See [References.yml](References.yml) for more details.
Symfony configuration parameters and environment variables can be used as values for these manually-created references.
Last but not least, it is possible to create references via a command-line option for the `migrate` command.

### Debugging references

It is possible to dump references to screen for debug purposes. See [References.yml](References.yml)


## Other

For more information please see the DSL definitions in the `Resources/doc/DSL` folder.

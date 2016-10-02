About the Migration Domain Specific Language (DSL)
==================================================

This directory contains the definitions of each action in the migration DSL, in the form of a commented Yaml file.

Specific topics are covered below.

*NB* For more examples of valid Yaml files describing migrations, have a look in the directory Tests/dsl/good

## Content language

By default the bundle uses eng-GB for creating all multilingual entities (contents, contentTypes, users, etc...). 
In order to create content in a different language, either specify it in your yml definition files (recommended), or
use a command-line switch.


## Importing binary files

To import binary files like images into attributes (ezimage, ezbinaryfile fields) the attribute needs to be defined as
a complex type to tell the system to handle it differently.

Below is a snippet of how a simple/primitive field type is defined in a content creation Yaml definition:

    attributes:
        - title: 'Example title'

To define a complex attribute we need to indicate the type of the attribute and then provide the required data fields for
the attribute.

Below is an example snippet for ezimage:

    attributes:
        - image:
            type: ezimage
            path: /path/to/the/image.jpg
            alt_text: 'Example alt text'

Images __have__ to be placed in the `MigrationVersions/images` folder.
Binary files __have__ to be placed in the `MigrationVersions/files` folder.

The paths to files/images in the definition are relative paths from the MigrationVersions/images or
MigrationVersions/files folders.
For example using the path from the snippet above the system would look for the image file in
`MigrationVersions/images/path/to/the/image.jpg` in the bundle's directory.

Please see the `ManageContent.yml` DSL definition file in the `Resources/doc/DSL` folder for more information.


## Using references in your migration files

The Yaml definitions support setting references of values of certain attributes, that you can retrieve in the subsequent
instructions.
For example, you could set a reference to a folder's location id and then use that as the parent location when creating
articles in that folder.

See the following example on using references:
the first step creates a new content type in the system and sets a reference to its id;
the second step adds a new policy to the editor role to allow editors to create objects of the new content
type under the location with id 2.

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
                searchable: true
                info-collector: false
                disable-translation: false
                default-value: New section page
            -
                type: ezxmltext
                name: Body
                identifier: body
                required: false
                searchable: true
                info-collector: false
                disable-translation: false
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
`reference:`. This instructs the system to look in the stored references for attributes that support this.

> **Important**: Please note that the reference **must be a quoted string**, as `reference:<reference_name>` uses
> YAML reserved characters.
>
> **Bad**: `some_key: reference:foo`<br>
> **Good**: `some_key: 'reference:foo'`

Currently you can use references to store the following values:

-   content
    -   `content_id`
    -   `content_remote_id`
    -   `location_id` (on content updates, the main location is returned)
    -   `path` (on content updates, the main location path is returned)
-   content type
    -   `content_type_id`
    -   `content_type_identifier`
-   location
    -   `location_id`
    -   `location_remote_id`
    -   `path`
-   role
    -   `role_id`
    -   `role_identifier`
-   user group
    -   `user_group_id`
-   user
    -   `user_id`
- language
    -   `language_id`

You can use references to set the following values:

-   content
    -   `content_type_identifier`
    -   `parent_location_id`
-   location
    -   `object_id` (The id of the content whose locations you want to manage)
    -   `remote_id` (The remote id of the content whose locations you want to manage)
    -   `parent_location_id` (The list of parent locations where the new locations need to be created)
    -   `location_id_to_swap` the current location with (Only on update actions)
-   role
    -   `limitation_values`
-   user group
    - `parent_user_group_id`
-   user
    - `user_group_id`

For more information please see the DSL definitions in the `Resources/doc/DSL` folder.


## References in the XML for the eZXMLText Field

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
This is done to minimize the chances that some random bits of text get modified by error.

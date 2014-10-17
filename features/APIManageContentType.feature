Feature: Manage content type migration through the eZ Publish 5 Public API
  As a developer
  I want to easily define content type migrations
  So that I don't have to write code that uses the Public API

Scenario: Create new field definition
  Given the identifier "title"
    And the type "ezstring"
    And the langueCode "eng-GB"
    And the name "Title"
    And "required" is set to "true"
    And "info-collerctor" is not set
    And "searchable" is set to "false"
    And "disable-translation" is set to "true"
  When I create a "FieldDefinitionCreateStruct"
  Then I should get an object of "FieldDefinitionCreateStruct"
    And the attribute "fieldTypeIdentifier" is set to "ezstring"
    And the attribute "identifier" is set to "title"
    And the attribute "names" is set to "Title" for language "eng-GB"
    And the attribute "isRequired" is set to "true"
    And the attribute "isSearchable" is set to "false"
    And the attribute "isTranslatable" is set to "false"
    And the attribute "isInfoCollector" is set to "false"

Scenario: Create new content type
  Given I have the parsed DSL:
        |mode  |type        |class_group|name            |identifier      |name_pattern|is_container|attributes|
        |create|content_type|1          |New content type|new_content_type|<title>     |true        |          |
    # Please inject this array into the array created by the Given step
    # An empty cell means that the system should use the default value
    And the attributes:
        |name  |identifier|type     |description|required|searchable|info-collector|category|default-value|
        |Title |title     |ezstring |           |        |          |              |        |             |
        |Author|author    |ezstring |           |        |          |              |        |             |
        |Body  |body      |ezxmltext|           |        |          |              |        |             |
  When I create a "ContentTypeCreateStruct"
  Then I should get an object of "ContentTypeCreateStruct"
    And it should have 3 fieldDefinitions

Scenario: Update content type
  Given I have the parsed DSL:
        |mode  |type        |id|name    |attributes|
        |update|content_type|2 |New name|          |
    # Please inject the attributes into the array created by the Given step
    # If a cell in the table is left empty then the old value should stay if updating an existing attribute
    # if adding a new attribute an empty value means that the system should use the default
    And the attributes:
        |identifier|type    |name    |description  |required|searchable|info-collector|category|default-value|
        |body      |        |The Body|A description|        |          |              |        |             |
        |new_attr  |ezstring|NewAttr |             |false   |false     |              |        |             |
  When I create a "ContentTypeUpdateStruct"
  Then I should get an object of "ContentTypeUpdateStruct"
    And the attribute "name" is set to "New name"
    And it should have 2 fieldDefinitions

#Needs to be mocked or needs an instantiated db
#http://extensions.behat.org/symfony2-mocker/
Scenario: Delete content type
  Given I have the parsed DSL:
        |mode  |type        |id|
        |delete|content_type|42|
  When I delete
  Then I should not have a content type with id 42

#Needs to be mocked or needs an instantiated db
#http://extensions.behat.org/symfony2-mocker/
Scenario: Delete content type
  Given I have the parsed DSL:
        |mode  |type        |identifier             |
        |delete|content_type|content_type_identifier|
  When I delete
  Then I should not have a content type with "content_type_identifier"
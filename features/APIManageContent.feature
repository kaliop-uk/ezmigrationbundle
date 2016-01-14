# This feature will probably require the full stack to be initialised with a database present as well
Feature: Manage content migration through the eZ Publish 5 Public API
  As a developer
  I want to easily define content migrations
  So that I don't have to write code that uses the Public API

Scenario: Create new content
  Given I have the parsed DSL:
        |mode  |type   |content_type  |location|attributes|
        |create|content|article       |2       |          |
    # Please inject the attributes array into the array created by the Given step
    And the attributes:
      |title        |author    |intro      |body     |
      |Article title|The Author|Teaser text|Body text|
  When I create a "ContentCreateStruct"
  Then I should get an object of "ContentCreateStruct"
    And it should have 4 fields of type "Field"
    And the fields should have the values:
      |fieldDefIdentifier|value                                                                                      |languageCode|
      |title             |Article title                                                                              |null        |
      |author            |The Author                                                                                 |null        |
      |intro             |<?xml version="1.0" encoding="utf-8"?><section><paragraph>Teaser text</paragraph></section>|null        |
      |body              |<?xml version="1.0" encoding="utf-8"?><section><paragraph>Body text</paragraph></section>  |null        |

Scenario: Update existing content identified by object id
  Given I have the parsed DSL:
        |mode  |type   |object_id|attributes|
        |update|content|42       |          |
    # Please inject the attributes array into the array created by the Given step
    And the attributes:
      |title    |author    |
      |New title|New Author|
  When I create a "ContentUpdateStruct"
  Then I should get an object of "ContentUpdateStruct"
    And it should have 2 fields of type "Field"
    And the fields should have the values:
      |fieldDefIdentifier|value     |languageCode|
      |title             |New title |null        |
      |author            |New Author|null        |

Scenario: Update existing content identified by remote id
  Given I have the parsed DSL:
        |mode  |type   |remote_id|attributes|
        |update|content|42       |          |
  # Please inject the attributes array into the array created by the Given step
    And the attributes:
      |title              |author              |
      |New Remote ID title|New Remote ID Author|
  When I create a "ContentUpdateStruct"
  Then I should get an object of "ContentUpdateStruct"
    And it should have 2 fields of type "Field"
    And the fields should have the values:
      |fieldDefIdentifier|value               |languageCode|
      |title             |New Remote ID title |null        |
      |author            |New Remote ID Author|null        |

# Not sure about this Scenario. It needs to be tried out and removed if too complex to test.
# Hopefully it can be mocked away using http://extensions.behat.org/symfony2-mocker/
#Scenario: Delete content identified by object id
#  Given the mode "delete"
#    And the type "content"
#    And the object_id "42"
#  When I delete
#  Then I should no longer have an object with id "42" in the database

# Not sure about this Scenario. It needs to be tried out and removed if too complex to test.
#Scenario: Delete content identified by remote id
#  Given the mode "delete"
#    And the type "content"
#    And the remote_id "42"
#  When I delete
#  Then I should no longer have an object with remote id "42" in the database

# Need to figure out how to pass a one dimensional array as a parameter
#Scenario: Delete multiple content at once using object ids
#  Given The mode "delete"
#    And The type "content"
#    And An array of object ids
#  When I delete
#  Then I should no longer have objects with ids
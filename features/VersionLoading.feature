Feature: Load version files from bundles
  In order to do migrations
  I want to be able to load version files
  from bundles and not from one central location

  Scenario: Load PHP version files on a per bundle basis
    Given there is a PHP version file in a bundle
    When the migration command is executed
    Then the version file should be loaded from the bundle

  Scenario: Load Yaml version files on a per bundle basis
    Given there is a Yaml version file in a bundle
    When the migration command is executed
    Then the version file should be loaded from the bundle
Kaliop eZ-Migration Bundle
==========================

This bundle makes it easy to handle eZPlatform / eZPublish 5 content upgrades/migrations.

It is inspired by the DoctrineMigrationsBundle ( http://symfony.com/doc/current/bundles/DoctrineMigrationsBundle/index.html )


## Installation

In either `require` or `require-dev` at the end of the bundle list add:

    "kaliop/ezmigrationbundle": "^2.0"

Save composer.json and run

    composer update --dev kaliop/migration

This will install the bundle and all its dependencies.

Please make sure that you have the bundle registered in the kernel as well. Check `ezpublish/EzPublishKernel.php`

The `registerBundles` method should look similar to:

    public function registerBundles()
    {
        $bundles = array(
            ... more stuff here ...
            new \Kaliop\eZMigrationBundle\EzMigrationBundle()
        );
    }

### Checking that the bundle is installed correctly

If you run `php ezpublish/console` you should see 4 new commands in the list:

    kaliop
      kaliop:migration:generate
      kaliop:migration:status
      kaliop:migration:migrate
      kaliop:migration:migration

This indicates that the bundle has been installed and registered correctly.

Note: the command `kaliop:migration:update` is kept around for compatibility, and will be removed in future versions.

### Updating the bundle

To get the latest version, you can update the bundle to the latest available version by using `composer`

    composer update kaliop/ezmigrationbundle

### Upgrading from version 1.x to version 2

Please read the [dedicated documentation page](doc/Upgrading/1.x_to_2.0.md)

## Getting started

All commands accept the standard Symfony/eZ publish 5 options, although some of them might not have any effect on the
command's execution.

### Generating a new, empty migration definition file

The bundle provides a command to easily generate a new blank migration definition file, stored in a specific bundle.

For example:

    php ezpublish/console kaliop:migration:generate --format=yml MyProjectBundle

The above command will place a new yml skeleton file in the `MigrationVersion` directory of the MyProjectBundle bundle.

If the directory does not exists then the command will create it for you, as long as the bundle does exist and is registered.
If the command is successful it will create a new yml file named with the following pattern: `YYYYMMDDHHMMSS_placeholder.yml`.
You are encouraged to rename the file and change the `place_holder` part to something more meaningful, but please keep
the timestamp part and underscore, as well as the extension

(the contents of the skeleton Yaml file are stored as twig template)

### Listing all migrations and their status

To see all the migrations definitions available in the system and whether they have been applied or not simply run the
status command in your eZ Publish 5 root directory:

    php ezpublish/console kaliop:migration:status

The list of migrations which have been already applied is stored in the database, in a table named `kaliop_migrations`.
The bundle will automatically create the table if needed.
In case you need to use a different name for that table, you can change the Symfony parameter `ez_migration_bundle.table_name`.

### Applying migrations

To apply all available migrations run the migrate command in your eZ Publish 5 root directory:

     php ezpublish/console kaliop:migration:migrate

NB: if you just executed the above command and got an error message because the migration definition file that you had
just generated is invalid, do not worry - that is by design. Head on to the next paragraph...

### Editing migration files

So far so good, but what kind of actions can be actually done using a migration?

Each migration definition consists of a series of steps, where each step defines an action. 

In a Yaml migration, you can define the following types of actions:
- creation, update and deletion of Contents
- creation, update and deletion of Locations
- creation, update and deletion of Users
- creation, update and deletion of UserGroups
- creation, update and deletion of Roles
- creation, update and deletion of ContentTypes
- creation of Tags (from the Netgen Tags Bundle)

The docs describing all supported parameters are in the [DSL Language description](Resources/doc/DSL/README.md)

### Custom migrations

For more specific needs, you can also use 2 other types of migrations:
- SQL migrations
- PHP migrations

#### SQL migrations 

Example command to generate an SQL migration definition:

     php ezpublish/console kaliop:migration:generate MyBundle create-new-table --format=sql

This will create the following file, which you are free to edit:

    .../MyBundle/Migrations/2016XXYYHHMMSS_mysql_create-new-table.sql

*NB* if you rename the sql file, keep in mind that the type of database to which it is supposed to apply is the part
of the filename between the first and second underscore characters.
If you later try to execute that migration on an eZPublish installation running on, say, PostgreSQL, the migration
will fail. You are of course free to create a specific SQL migration for a different database type.
 
The Migration bundle itself imposes no limitations on the type of databases supported, but as it is based on the
Doctrine DBAL, it will only work on the databases that Doctrine supports.

#### PHP migrations

If the type of manipulation that you need to do is too complex for either YML or SQL, you can use a php class as
migration definition. To generate a PHP migration definition, execute: 

     php ezpublish/console kaliop:migration:generate MyBundle AMigrationClass --format=php

This will create the following file, which you are free to edit:

    .../MyBundle/Migrations/2016XXYYHHMMSS_AMigrationClass.php

As you can see in the generated definition, the php class to be used for a migration needs to implement a specific
interface. The Symfony DIC container is passed to the migration class so that it can access from it all the services,
parameters and other thing that it needs.

For a more detailed example of a migration definition done in PHP, look in the MigrationVersions folder of this very bundle.
  
*NB* if you rename the php file, keep in mind that the filename and the name of the class it contains are tied - the
standard autoloading mechanism of the application does not apply when loading the migration definition. This is also
the reason why the php classes used as migrations should not use namespaces. 

### Re-executing failed migrations

The easiest way to re-execute a migration in 'failed' status, is to remove it from the migrations table:

    php ezpublish/console kaliop:migration:migration migration_name --delete

After removing the information about the migration form the migrations table, running the `migrate` command will execute it again.


## Extending the bundle

### Supporting custom migrations

The bundle has been designed to be easily extended in many ways, such as:
* adding support for custom/complex field-types
* adding support for completely new actions in the Yml definitions
* adding support for a new file format for storing migration definitions
* taking over the way that the migrations definitions are loaded from the filesystem or stored in the database
* etc... 

Following Symfony best practices, for the first 3 options in the list above all you need to do is to create a service
and give it an appropriate tag (the class implementing service should of course implement an appropriate interface). 

To find out the names of the tags that you need to implement, as well as for all the other services which you can
override, take a look at the [services.yml file](Resources/config/services.yml).


### Running tests

*NB: the testing framework is known to be broken in the current release. It will be fixed as soon as possible*

The bundle has both unit tests and BDD features.

To run the unit tests just point PHPUnit to the bundle directory:

    bin/phpunit vendor/kaliop/ezmigrationbundle

The Behat instructions are left here for future reference when we get it working correctly with eZ Publish 5.
To run the BDD test with Behat:

    bin/behat @KaliopBundleMigrationBundle

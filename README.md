Kaliop eZ-Migration Bundle
==========================

This bundle makes it easy to programmatically deploy changes to eZPlatform / eZPublish 5 database structure and contents.

It is inspired by the [DoctrineMigrationsBundle](http://symfony.com/doc/current/bundles/DoctrineMigrationsBundle/index.html)

You can think of it as the grandson of the legacy [ezxmlinstaller](https://github.com/ezsystems/ezxmlinstaller) extension.


## Requirements

* PHP 5.4 or later.

* eZPublish Enterprise 5.3 or Community 2014.3 or later.


## Installation

In either `require` or `require-dev` at the end of the bundle list in the composer.json file add:

    "kaliop/ezmigrationbundle": "^2.0"

Save it and run

    composer update --dev kaliop/ezmigrationbundle

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

All commands accept the standard Symfony/eZPublish 5 options, although some of them might not have any effect on the
command's execution.

### Generating a new, empty migration definition file

The bundle provides a command to easily generate a new blank migration definition file, stored in a specific bundle.

For example:

    php ezpublish/console kaliop:migration:generate --format=yml MyProjectBundle

The above command will place a new yml skeleton file in the `MigrationVersion` directory of the MyProjectBundle bundle.

If the directory does not exists then the command will create it for you, as long as the bundle does exist and is registered.
If the command is successful it will create a new yml file named with the following pattern: `YYYYMMDDHHMMSS_placeholder.yml`.
You are encouraged to rename the file and change the `placeholder` part to something more meaningful, but please keep
the timestamp part and underscore, as well as the extension

(the contents of the skeleton Yaml file are stored as twig template)

### Listing all migrations and their status

To see all the migrations definitions available in the system and whether they have been applied or not simply run the
status command in your eZPublish 5 root directory:

    php ezpublish/console kaliop:migration:status

The list of migrations which have been already applied is stored in the database, in a table named `kaliop_migrations`.
The bundle will automatically create the table if needed.
In case you need to use a different name for that table, you can change the Symfony parameter `ez_migration_bundle.table_name`.

### Applying migrations

To apply all available migrations run the migrate command in your eZPublish 5 root directory:

     php ezpublish/console kaliop:migration:migrate

NB: if you just executed the above command and got an error message because the migration definition file that you had
just generated is invalid, do not worry - that is by design. Head on to the next paragraph...

#### Applying a single migration file

To apply a single migration run the migrate command passing it the path to its definition, as follows:

    php ezpublish/console kaliop:migration:migrate --path=src/MyNamespace/MyBundle/MigrationsVersions/20160803193400_a_migration.yml

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
- creation and deletion of Languages
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


## Usage of transactions / rolling back changes

By default the bundle runs each migration in a database transaction.
This means that if a step fails, all of the previous steps get rolled back, and the database is left at its previous state.
This is a safety feature built in by design; if you prefer the migration steps to be executed in separate transactions
the easiest way is to create a separate migration file for each step.

Note also that by default the `migrate` command stops on the 1st failed migration, but it can be executed with a flag
to allow it to continue and execute all available migrations even in case of failures.

As for rolling back changes: given the nature of the eZPublish API, rolling back changes to Content is not an easy feat.
As such, the bundle does not provide built-in support for rolling back the database to the version it had before
applying a given migration. We recommend always taking a database snapshot before applying migrations, and use it in
case you need to roll back your changes. Another approach consists in writing a separate migration to undo the changes. 


## Customizing the migration logic via Event Listeners

An easy way to hook up custom logic to the execution of migration steps - without having to implement your own
customized action executors - is to use Event Listeners.

Two events are fired *for each step* during execution of migrations:

    * ez_migration.before_execution => listeners receive a BeforeStepExecutionEvent event instance
    * ez_migration.step_executed => listeners receive a StepExecutedEvent event instance

In order to act on those events, you will need to declare tagged services, such as for ex:

    my.step_executed_listener:
        class: my\helper\StepExecutedListener
        tags:
            - { name: kernel.event_listener, event: ez_migration.step_executed, method: onStepExecuted }

and the corresponding php class:

    use Kaliop\eZMigrationBundle\API\Event\StepExecutedEvent;
    
    class StepExecutedListener
    {
        public function onStepExecuted(StepExecutedEvent $event)
        {
            // do something...
        }
    }


## Known Issues and limitations

* if you get fatal errors when running a migration stating that a node or object has not been found, it is most likely
    related to how the dual-kernel works in eZPublish, and the fact that the legacy and Symfony kernels use a separate
    connection to the database. Since the migration bundle by default wraps all database changes for a migration in a
    database transaction, when the Slots are fired which allow the legacy kernel to clear its caches, the legacy kernel
    can not see the database changes applied by the Symfony kernel, and, depending on the specific Slot in use, might
    fail with a fatal error.
    The simplest workaround is to disable usage of transactions by passing the `-u` flag to the `migrate` command.

* if you get fatal errors without any error message when running a migration which involves a lot of content changes,
    such as f.e. altering a contentType with many contents, it light be that you are running out of memory for your
    php process.
    Known workarounds involve:
    - increase the maximum amount of memory allowed for the php script by running it with option '-d memory_limit=-1'
    - execute the migration command using a Symfony environment which has reduced logging and kernel debug disabled:
        the default configuration for the `dev` environment is known to leak memory

* the bundle does not at the moment support creation of user accounts using a custom contentType

* the bundle at the moment does not support creating entities with a creator other than user id 14 ('admin')

* if you are using eZPublish versions prior to 2015.9, you will not be able to create/update Role definitions that
    contain policies with limitations for custom modules/functions. The known workaround is to take over the
    RoleService and alter its constructor to inject into it the desired limitations


## Extending the bundle

### Supporting custom migrations

The bundle has been designed to be easily extended in many ways, such as:
* adding support for custom/complex field-types
* adding support for completely new actions in the Yml definitions
* adding support for a new file format for storing migration definitions
* adding support for a new resolver for the custom references in the migration definitions
* taking over the way that the migrations definitions are loaded from the filesystem or stored in the database
* etc... 

Following Symfony best practices, for the first 4 options in the list above all you need to do is to create a service
and give it an appropriate tag (the class implementing service should of course implement an appropriate interface). 

To find out the names of the tags that you need to implement, as well as for all the other services which you can
override, take a look at the [services.yml file](Resources/config/services.yml).

### Running tests

The bundle uses both PHPUnit and Behat to run functional tests.
*NB: the Behat tests are known to be broken in the current release. This will be fixed as soon as possible*

To run the PHPUnit tests:

    export KERNEL_DIR=ezpublish
    export SYMFONY_ENV=behat (or whatever your test env is)

    bin/phpunit vendor/kaliop/ezmigrationbundle

To run the Behat tests:

    bin/behat -c vendor/kaliop/ezmigrationbundle/behat.yml


[![License](https://poser.pugx.org/kaliop/ezmigrationbundle/license)](https://packagist.org/packages/kaliop/ezmigrationbundle)
[![Latest Stable Version](https://poser.pugx.org/kaliop/ezmigrationbundle/v/stable)](https://packagist.org/packages/kaliop/ezobjectwrapperbundle)
[![Total Downloads](https://poser.pugx.org/kaliop/ezmigrationbundle/downloads)](https://packagist.org/packages/kaliop/ezmigrationbundle)

[![Build Status](https://travis-ci.org/kaliop-uk/ezmigrationbundle.svg?branch=master)](https://travis-ci.org/kaliop-uk/ezmigrationbundle)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/kaliop-uk/ezmigrationbundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/kaliop-uk/ezmigrationbundle/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/kaliop-uk/ezmigrationbundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/kaliop-uk/ezmigrationbundle/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/7f16a049-a738-44ae-b947-f39401aec2d5/mini.png)](https://insight.sensiolabs.com/projects/7f16a049-a738-44ae-b947-f39401aec2d5)

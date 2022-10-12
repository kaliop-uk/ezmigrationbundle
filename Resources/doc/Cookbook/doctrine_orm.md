Using Kaliop Migrations to manage Doctrine ORM tables
=====================================================

If you are using the Doctrine ORM framework to manage custom data in your eZ application, there are good chances are that
you are using the `doctrine:schema:*` commands in order to create/update/drop the corresponding database structure.

In such scenario, it might be worth taking advantage of the Kaliop Migrations bundle to make sure that any changes
applied to the DB because of the ORM requirements are properly tracked using the same mechanisms as all other changes
to the database's CMS structure and data required by evolution of the application.

In order to achieve that, a simple procedure is to:

1. use the doctrine `schema:update` command to generate the SQL code with schema changes and save it into a Migration file

        php bin/console doctrine:schema:update --dump-sql | grep -v 'The following SQL statements will be executed' > src/MyApp/MyBundle/MigrationVersions/$(date +%Y%m%d%H%M%S)_mysql_orm_updates.sql

2. run all migrations

        php bin/console kaliop:migrations:migrate

The above should be easy to integrate as part of CI pipelines or deployment scripts.

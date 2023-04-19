<?php

include_once(__DIR__.'/MigrationExecutingTest.php');

use Kaliop\eZMigrationBundle\API\Value\Migration;

/**
 * Tests transaction handling
 */
class TransactionsTest extends MigrationExecutingTest
{
    /**
     * Test executing a transaction-committing migration without the `-u` option: wrap it in a db transaction.
     * This is known to happen f.e. with php >= 8.0 and mysql, when the migration contains ddl statements, which
     * make the wrapping transaction be committed directly by the db. We check that the code which handles
     * the wrapping transaction in the MigrationService can cope with that
     *
     * @todo skip when db is not mysql
     */
    public function testMysqlAutocommit()
    {
        $this->runMigration($this->dslDir.'/transactions/UnitTestOK1011_mysql_create_table.sql');

        $this->runMigration($this->dslDir.'/transactions/UnitTestOK1012_mysql_drop_table.sql');
    }

    /**
     * Test the migration rollback: a failed migration should not leave data in the db
     */
    public function testRollback()
    {
        $this->runMigration($this->dslDir.'/transactions/UnitTestOK1021_create_table.yml');

        $m = $this->runMigration($this->dslDir.'/transactions/UnitTestOK1022_faulty_sql.yml', [], Migration::STATUS_FAILED, false);
        $this->assertStringContainsString('Error in execution of step 2', $m->executionError);

        $this->runMigration($this->dslDir.'/transactions/UnitTestOK1023_check_data.yml');

        $this->runMigration($this->dslDir.'/transactions/UnitTestOK1024_drop_table.yml');
    }

    // pending transaction opened via pdo
    public function testPendingTransaction()
    {
        $m = $this->runMigration($this->dslDir.'/transactions/UnitTestOK1031_BeginTransactionClass.php', [], Migration::STATUS_FAILED, false);
        $this->assertStringContainsString('The migration was rolled back because it had left a database transaction pending', $m->executionError);

        $m = $this->runMigration($this->dslDir.'/transactions/UnitTestOK1031_BeginTransactionClass.php', ['-u' => true], Migration::STATUS_FAILED, false);
        $this->assertStringContainsString('The migration was rolled back because it had left a database transaction pending', $m->executionError);
    }

    // pending transaction opened via sql
    public function testPendingTransaction2()
    {
        // this works because the 'extra' sql transaction gets committed at the end of the migration by our wrapping transaction
        $m = $this->runMigration($this->dslDir.'/transactions/UnitTestOK1032_begin_transaction.yml');

        /// @todo make this work... atm the migration is left in executing status
        //$m = $this->runMigration($this->dslDir.'/transactions/UnitTestOK1032_begin_transaction.yml', ['-u' => true], Migration::STATUS_FAILED, false);
        //$this->assertStringContainsString('The migration was rolled back because it had left a database transaction pending', $m->executionError);
    }
}

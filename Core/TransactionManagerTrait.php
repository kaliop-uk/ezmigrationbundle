<?php

namespace Kaliop\eZMigrationBundle\Core;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use ProxyManager\Proxy\ValueHolderInterface;

/**
 * Functionality related to managing transactions - both "repo transactions" and "db transactions"
 *
 * Implemented as a trait to avoid breaking BC as much as possible.
 * @todo transform this into an interface + a service, when bumping a major version
 */
trait TransactionManagerTrait
{
    /** @var Repository $repository */
    protected $repository;

    /** @var \Doctrine\DBAL\Connection $connection */
    protected $connection;

    public function setRepository(Repository $repository)
    {
        // NB: ideally we should retrieve the DB connection from the Repository. But that means going through
        // multiple steps of access to protected/private members (persistenceHandler / transactionHandler / ...) which
        // are not guaranteed to be stable, or even present. So we inject th connection separately...

        $this->repository = $repository;
    }

    public function setConnection(DatabaseHandler $dbHandler)
    {
        $this->connection = $dbHandler->getConnection();
    }

    /**
     * Repo transaction
     * @return void
     */
    protected function beginTransaction()
    {
        $this->repository->beginTransaction();
    }

    /**
     * Repo transaction
     * @return void
     */
    protected function commit()
    {
        $this->repository->commit();
    }

    /**
     * Repo transaction
     * @return void
     */
    protected function rollback()
    {
        $this->repository->rollback();
    }

    /**
     * Resets the transaction counter and all other transaction-related info for the current db connection.
     * To be used only when we know for sure the db has no active transactions, and the DBAL object is out of sync
     * @internal
     * @return void
     */
    protected function resetDBTransaction()
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = ($this->connection instanceof ValueHolderInterface) ? $this->connection->getWrappedValueHolderValue() : $this->connection;
        $cl = \Closure::bind(function () {
            $this->transactionNestingLevel = 0;
            $this->isRollbackOnly = false;
        },
            $connection,
            $connection
        );
        $cl();
    }

    /**
     * Returns the current db transaction nesting level.
     *
     * @return int The nesting level. A value of 0 means there's no active transaction.
     */
    public function getDBTransactionNestingLevel()
    {
        return $this->connection->getTransactionNestingLevel();
    }

    public function rollbackDBTransaction()
    {
        return $this->connection->rollBack();
    }
}

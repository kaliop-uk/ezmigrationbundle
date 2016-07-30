<?php


namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

class SQLExecutor extends AbstractExecutor
{
    /**
     * @var DatabaseHandler $connection
     */
    protected $connection;

    protected $supportedStepTypes = array('sql');

    /**
     * @param DatabaseHandler $connection
     */
    public function __construct(DatabaseHandler $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param MigrationStep $step
     * @return void
     */
    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        /// @todo !!!
    }
}

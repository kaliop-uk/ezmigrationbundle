<?php


namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

class SQLExecutor extends AbstractExecutor
{
    /**
     * @var DatabaseHandler $connection
     */
    protected $dbHandler;

    protected $supportedStepTypes = array('sql');

    /**
     * @param DatabaseHandler $dbHandler
     */
    public function __construct(DatabaseHandler $dbHandler)
    {
        $this->dbHandler = $dbHandler;
    }

    /**
     * @param MigrationStep $step
     * @return void
     * @throws \Exception if migration step is not for this type of db
     */
    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        $conn = $this->dbHandler->getConnection();
        // @see http://doctrine-orm.readthedocs.io/projects/doctrine-dbal/en/latest/reference/platforms.html
        $dbType = strtolower(preg_replace('/([0-9]+|Platform)/', '', $conn->getDatabasePlatform()->getName()));

        $dsl = $step->dsl;

        if (!isset($dsl[$dbType])) {
            throw new \Exception("Current database type '$dbType' is not supported by the SQL migration");
        }
        $sql = $dsl[$dbType];

        return $conn->exec($sql);
    }
}

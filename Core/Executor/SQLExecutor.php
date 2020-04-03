<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Doctrine\DBAL\Connection;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ReferenceBagInterface;

class SQLExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    /** @var \Doctrine\DBAL\Connection */
    protected $connection;

    protected $supportedStepTypes = array('sql');

    /** @var ReferenceBagInterface $referenceResolver */
    protected $referenceResolver;

    /**
     * @param \Doctrine\DBAL\Connection $connection
     * @param ReferenceBagInterface $referenceResolver
     */
    public function __construct(Connection $connection, ReferenceBagInterface $referenceResolver)
    {
        $this->connection = $connection;
        $this->referenceResolver = $referenceResolver;
    }

    /**
     * @param MigrationStep $step
     * @return integer
     * @throws \Exception if migration step is not for this type of db
     */
    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        $this->skipStepIfNeeded($step);

        $conn = $this->connection;
        // @see http://doctrine-orm.readthedocs.io/projects/doctrine-dbal/en/latest/reference/platforms.html
        $dbType = strtolower(preg_replace('/([0-9]+|Platform)/', '', $conn->getDatabasePlatform()->getName()));

        $dsl = $step->dsl;

        if (!isset($dsl[$dbType])) {
            throw new \Exception("Current database type '$dbType' is not supported by the SQL migration");
        }
        $sql = $dsl[$dbType];

        // returns the number of affected rows
        $result = $conn->exec($sql);

        $this->setReferences($result, $dsl);

        return $result;
    }

    protected function setReferences($result, $dsl)
    {
        if (!array_key_exists('references', $dsl)) {
            return false;
        }

        foreach ($dsl['references'] as $reference) {
            switch ($reference['attribute']) {
                case 'affected_rows':
                    $value = $result;
                    break;
                default:
                    throw new \InvalidArgumentException('Sql Executor does not support setting references for attribute ' . $reference['attribute']);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
        }
    }
}

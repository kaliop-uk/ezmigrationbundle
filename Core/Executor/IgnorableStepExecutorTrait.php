<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\MigrationStepSkippedException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

trait IgnorableStepExecutorTrait
{
    protected $referenceMatcher;

    public function setReferenceMatcher($referenceMatcher)
    {
        $this->referenceMatcher = $referenceMatcher;
    }

    /**
     * @param MigrationStep $step
     * @return void
     * @throws MigrationStepSkippedException
     */
    protected function skipStepIfNeeded(MigrationStep $step)
    {
        if (isset($step->dsl['if'])) {
            if (!$this->matchConditions($step->dsl['if'])) {
                throw new MigrationStepSkippedException();
            }
        }
    }

    protected function matchConditions($conditions)
    {
        $match = $this->referenceMatcher->match($conditions);
        if ($match instanceof \ArrayObject) {
            $match = $match->getArrayCopy();
        }
        return reset($match);
    }

}

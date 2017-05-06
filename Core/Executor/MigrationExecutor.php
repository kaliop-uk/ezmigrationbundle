<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ReferenceBagInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationAbortedException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationSuspendedException;
use Symfony\Component\Validator\Constraints;

class MigrationExecutor extends AbstractExecutor
{
    protected $supportedStepTypes = array('migration');
    protected $supportedActions = array('cancel', 'suspend');

    /** @var \Kaliop\eZMigrationBundle\Core\MigrationService $migrationService */
    //protected $migrationService;

    protected $referenceMatcher;

    public function __construct($referenceMatcher)
    {
        //$this->migrationService = $migrationService;
        $this->referenceMatcher = $referenceMatcher;
    }

    /**
     * @param MigrationStep $step
     * @return mixed
     * @throws \Exception
     */
    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        if (!isset($step->dsl['mode'])) {
            throw new \Exception("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new \Exception("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        return $this->$action($step->dsl, $step->context);
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return true
     * @throws \Exception
     */
    protected function cancel($dsl, $context)
    {
        $message = isset($dsl['message']) ? $dsl['message'] : '';

        if (isset($dsl['if'])) {
            /// @todo ...
        }

        throw new MigrationAbortedException($message);
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return true
     * @throws \Exception
     */
    protected function suspend($dsl, $context)
    {
        $message = isset($dsl['message']) ? $dsl['message'] : '';

        if (!isset($dsl['until'])) {
            throw new \Exception("An until condition is required to suspend a migration");
        }

        if ($matched = $this->matchConditions($dsl['until'])) {
            // the time has come to resume!
            // q: return timestamp, matched condition or ... ?
            return $matched;
        }

        throw new MigrationSuspendedException($message);
    }

    protected function matchConditions($conditions)
    {
        foreach ($conditions as $key => $values) {

            /*if (!is_array($values)) {
                $values = array($values);
            }*/

            switch ($key) {
                case 'date':
                    return time() >= $values;

                case 'match':
                    return reset($this->referenceMatcher->match($values));

                default:
                    throw new \Exception("Unknown until condition: '$key' when suspending a migration");
            }
        }
    }
}

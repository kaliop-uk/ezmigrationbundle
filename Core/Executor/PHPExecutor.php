<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

class PHPExecutor extends AbstractExecutor
{
    protected $supportedStepTypes = array('php_method', 'php_class', 'symfony_service');

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
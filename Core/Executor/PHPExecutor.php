<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PHPExecutor extends AbstractExecutor
{
    protected $supportedStepTypes = array('php');
    protected $mandatoryInterface = 'Kaliop\eZMigrationBundle\API\MigrationInterface';
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param MigrationStep $step
     * @return void
     * @throws \Exception
     */
    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        $dsl = $step->dsl;

        if (!isset($dsl['class'])) {
            throw new \Exception("Missing 'class' for php migration step");
        }
        $class = $dsl['class'];

        if (!class_exists($class) && isset($step->context['path']))
        {
            if (!is_file($step->context['path'])) {
                throw new \Exception("Missing file '{$step->context['path']}' for php migration step to load class definition from");
            }

            include_once($step->context['path']);
        }

        if (!class_exists($class)) {
            throw new \Exception("Class '$class' for php migration step does not exist");
        }

        $interfaces = class_implements($class);
        if (!in_array($this->mandatoryInterface, $interfaces)) {
            throw new \Exception("The migration definition class '$class' should implement the interface '{$this->mandatoryInterface}'");
        }

        return call_user_func(array($class, 'execute'), $this->container);
    }
}

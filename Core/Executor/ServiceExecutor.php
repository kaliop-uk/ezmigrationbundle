<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ServiceExecutor extends BasePHPExecutor
{
    protected $supportedStepTypes = array('service');
    protected $supportedActions = array('call');

    protected $container;

    public function __construct(ContainerInterface $container, ReferenceResolverBagInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
        $this->container = $container;
    }

    /**
     * @param MigrationStep $step
     * @return mixed
     * @throws InvalidStepDefinitionException
     */
    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        /// @todo we could be kind and assume 'call' by default
        if (!isset($step->dsl['mode'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new InvalidStepDefinitionException("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        $this->skipStepIfNeeded($step);

        return $this->$action($step->dsl, $step->context);
    }

    /**
     * @param $dsl
     * @param $context
     * @return mixed
     * @throws InvalidStepDefinitionException
     */
    protected function call($dsl, $context)
    {
        if (!isset($dsl['service'])) {
            throw new InvalidStepDefinitionException("Can not call service method: 'service' missing");
        }
        if (!isset($dsl['method'])) {
            throw new InvalidStepDefinitionException("Can not call service method: 'method' missing");
        }

        $service = $this->container->get($this->resolveReference($dsl['service']));
        $method = $this->resolveReference($dsl['method']);
        $callable = array($service, $method);
        if (!is_callable($callable)) {
            throw new InvalidStepDefinitionException("Can not call service method: $method is not a method of " . get_class($service));
        }

        $args = $this->getArguments($dsl);

        return $this->runCallable($callable, $args, $dsl);
    }
}

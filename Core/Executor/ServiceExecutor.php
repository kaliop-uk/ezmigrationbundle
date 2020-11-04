<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

class ServiceExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    protected $supportedStepTypes = array('service');
    protected $supportedActions = array('call');

    /** @var ReferenceResolverBagInterface $referenceResolver */
    protected $referenceResolver;

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
        if (isset($dsl['arguments']) && !is_array($dsl['arguments'])) {
            throw new InvalidStepDefinitionException("Can not call service method: 'arguments' is not an array");
        }

        $service = $this->container->get($this->referenceResolver->resolveReference($dsl['service']));
        $method = $this->referenceResolver->resolveReference($dsl['method']);
        $callable = array($service, $method);
        if (!is_callable($callable)) {
            throw new InvalidStepDefinitionException("Can not call service method: $method is not a method of " . get_class($service));
        }

        if (isset($dsl['arguments'])) {
            $args = $dsl['arguments'];
        } else {
            $args = array();
        }
        foreach($args as &$val) {
            $val = $this->resolveReferencesRecursively($val);
        }

        $exception = null;
        $result = null;
        try {
            $result = call_user_func_array($callable, $args);
        } catch (\Exception $exception) {
            $catch = false;

            // allow to specify a set of exceptions to tolerate
            if (isset($dsl['catch'])) {
                if (is_array($dsl['catch'])) {
                    $caught = $dsl['catch'];
                } else {
                    $caught = array($dsl['catch']);
                }

                foreach ($caught as $baseException) {
                    if (is_subclass_of($exception, $baseException)) {
                        $catch = true;
                        break;
                    }
                }
            }

            if (!$catch) {
                throw $exception;
            }
        }

        $this->setReferences($result, $exception, $dsl);

        return $result;
    }

    /**
     * @param $result
     * @param \Exception|null $exception
     * @param $dsl
     * @return bool
     * @throws InvalidStepDefinitionException
     */
    protected function setReferences($result, \Exception $exception = null, $dsl)
    {
        if (!array_key_exists('references', $dsl)) {
            return false;
        }

        foreach ($dsl['references'] as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch ($reference['attribute']) {
                case 'result':
                    $value = $result;
                    break;
                case 'exception_code':
                    $value = $exception ? $exception->getCode() : null;
                    break;
                case 'exception_message':
                    $value = $exception ? $exception->getMessage() : null;
                    break;
                case 'exception_file':
                    $value = $exception ? $exception->getFile() : null;
                    break;
                case 'exception_line':
                    $value = $exception ? $exception->getLine() : null;
                    break;

                default:
                    throw new InvalidStepDefinitionException('Service executor does not support setting references for attribute ' . $reference['attribute']);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }

    /**
     * @todo should be moved into the reference resolver classes
     */
    protected function resolveReferencesRecursively($match)
    {
        if (is_array($match)) {
            foreach ($match as $condition => $values) {
                $match[$condition] = $this->resolveReferencesRecursively($values);
            }
            return $match;
        } else {
            return $this->referenceResolver->resolveReference($match);
        }
    }
}

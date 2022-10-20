<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PHPExecutor extends BasePHPExecutor
{
    use IgnorableStepExecutorTrait;

    protected $supportedStepTypes = array('php');
    protected $supportedActions = array('call_function', 'call_static_method');

    protected $mandatoryInterface = 'Kaliop\eZMigrationBundle\API\MigrationInterface';
    protected $container;

    // we keep the referenceResolver optional for BC
    public function __construct(ContainerInterface $container, ReferenceResolverBagInterface $referenceResolver = null)
    {
        $this->container = $container;
        $this->referenceResolver = $referenceResolver;
    }

    /**
     * Runs the static method `execute` on a given class, passing in the container
     * @param MigrationStep $step
     * @return mixed
     * @throws \Exception
     */
    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        $dsl = $step->dsl;

        $checkInterface = false;

        if (isset($dsl['mode'])) {
            $action = $dsl['mode'];
        } else {
            // BC
            /// @todo we might improve the API by adding a different, specific action for this case
            $action = 'call_static_method';
            $dsl['method'] = 'execute';
            $dsl['arguments'] = array($this->container);
            $checkInterface = true;
        }

        if (!in_array($action, $this->supportedActions)) {
            throw new InvalidStepDefinitionException("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        $this->skipStepIfNeeded($step);

        $action = str_replace('_', '', ucwords($action, '_'));
        return $action === 'call_static_method' ? $this->$action($dsl, $step->context, $checkInterface) : $this->$action($dsl, $step->context);
    }

    protected function callFunction($dsl, $context)
    {
        if (!isset($dsl['function'])) {
            throw new InvalidStepDefinitionException("Missing 'function' for php migration step");
        }
        $function = $this->resolveReference($dsl['function']);
        if (!function_exists($function)) {
            throw new InvalidStepDefinitionException("Can not call function: $function is not a function");
        }

        $args = $this->getArguments($dsl);

        return $this->runCallable($function, $args, $dsl);
    }

    protected function callStaticMethod($dsl, $context, $checkInterface = false)
    {
        /// @todo resolve refs on class name, BUT without conflicting with the checks done in PHPDefinitionParser

        if (!isset($dsl['class'])) {
            throw new InvalidStepDefinitionException("Missing 'class' for php migration step");
        }
        $class = $dsl['class'];

        if (!class_exists($class) && isset($context['path']))
        {
            if (!is_file($context['path'])) {
                /// @todo check: do we get path set for a call_static_method mig defined in a yml file? It would
                ///       probably be a better idea to use a specific context element
                throw new InvalidStepDefinitionException("Missing file '{$context['path']}' for php migration step to load class definition from");
            }

            include_once($context['path']);
        }

        if (!class_exists($class)) {
            throw new MigrationBundleException("Class '$class' for php migration step does not exist");
        }

        if (!isset($dsl['method'])) {
            throw new InvalidStepDefinitionException("Missing 'method' for php migration step");
        }
        $method = $this->resolveReference($dsl['method']);

        $callable = array($class, $method);

        if ($checkInterface) {
            $interfaces = class_implements($class);
            if (!in_array($this->mandatoryInterface, $interfaces)) {
                throw new InvalidStepDefinitionException("The migration definition class '$class' should implement the interface '{$this->mandatoryInterface}'");
            }
        } else {
            if (!is_callable($callable)) {
                throw new InvalidStepDefinitionException("Can not call method: $method is not a method of " . $class);
            }
        }

        $args = $this->getArguments($dsl);

        return $this->runCallable($callable, $args, $dsl);
    }
}

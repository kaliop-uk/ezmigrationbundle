<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;

abstract class BasePHPExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    /** @var ReferenceResolverBagInterface $referenceResolver */
    protected $referenceResolver;

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

    protected function getArguments($dsl)
    {
        if (isset($dsl['arguments'])) {
            if (!is_array($dsl['arguments'])) {
                throw new InvalidStepDefinitionException("'arguments' is not an array in php migration step");
            }

            $args = $dsl['arguments'];

            foreach($args as &$val) {
                $val = $this->resolveReferencesRecursively($val);
            }
        } else {
            $args = array();
        }

        return $args;
    }

    protected function runCallable($callable, $args, $dsl)
    {
        $exception = null;
        $result = null;
        try {
            $result = call_user_func_array($callable, $args);
        } catch (\Exception $exception) {
            $this->handleException($exception, $dsl);
        }

        $this->setReferences($result, $exception, $dsl);

        return $result;
    }

    protected function handleException($exception, $dsl)
    {
        $catch = false;

        // allow to specify a set of exceptions to tolerate
        if (isset($dsl['catch'])) {
            if (is_array($dsl['catch'])) {
                $caught = $dsl['catch'];
            } else {
                $caught = array($dsl['catch']);
            }

            foreach ($caught as $baseException) {
                if (is_a($exception, $baseException)) {
                    $catch = true;
                    break;
                }
            }
        }

        if (!$catch) {
            throw $exception;
        }
    }
}

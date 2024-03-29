<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\Core\Process\ProcessBuilder;
use Symfony\Component\Process\Process;

/**
 * @property ReferenceResolverBagInterface $referenceResolver
 */
class ProcessExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;
    use ReferenceSetterTrait;

    protected $supportedStepTypes = array('process');
    protected $supportedActions = array('run');

    protected $defaultTimeout = 86400;

    /**
     * @param ReferenceResolverBagInterface $referenceResolver
     */
    public function __construct(ReferenceResolverBagInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
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
     * @param array|null $context
     * @return \Symfony\Component\Process\Process
     * @throws \Exception
     * @todo add more options supported by Sf Process
     */
    protected function run($dsl, $context)
    {
        if (!isset($dsl['command'])) {
            throw new InvalidStepDefinitionException("Can not run process: command missing");
        }

        $builder = new ProcessBuilder();

        // mandatory args and options
        $builderArgs = array($this->resolveReference($dsl['command']));

        if (isset($dsl['arguments'])) {
            foreach ($dsl['arguments'] as $arg) {
                /// @todo should this be recursive?
                $builderArgs[] = $this->resolveReference($arg);
            }
        }

        $process = $builder
            ->setArguments($builderArgs)
            ->getProcess();

        // allow long migrations processes by default
        $timeout = $this->defaultTimeout;
        if (isset($dsl['timeout'])) {
            $timeout = $this->resolveReference($dsl['timeout']);
        }
        $process->setTimeout($timeout);

        if (isset($dsl['working_directory'])) {
            $process->setWorkingDirectory($this->resolveReference($dsl['working_directory']));
        }

        /// @todo should we support false/true ?
        if (isset($dsl['disable_output'])) {
            $process->disableOutput();
        }

        if (isset($dsl['environment'])) {
            $process->setEnv($this->resolveReference($dsl['environment']));
        }

        $process->run();

        if (isset($dsl['fail_on_error']) && $this->resolveReference($dsl['fail_on_error'])) {
            if (($exitCode = $process->getExitCode()) != 0) {
                throw new MigrationBundleException("Process failed with exit code: $exitCode", $exitCode);
            }
        }

        $this->setReferences($process, $dsl);

        return $process;
    }

    /**
     * @param Process $process
     * @param $dsl
     * @return bool
     * @throws InvalidStepDefinitionException
     */
    protected function setReferences(Process $process, $dsl)
    {
        if (!array_key_exists('references', $dsl) || !count($dsl['references'])) {
            return false;
        }

        foreach ($dsl['references'] as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch ($reference['attribute']) {
                case 'error_output':
                    $value = rtrim($process->getErrorOutput(), "\r\n");
                    break;
                case 'exit_code':
                    $value = $process->getExitCode();
                    break;
                case 'output':
                    $value = rtrim($process->getOutput(), "\r\n");
                    break;
                default:
                    throw new InvalidStepDefinitionException('Process executor does not support setting references for attribute ' . $reference['attribute']);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $this->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Symfony\Component\Process\Process;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\Core\Process\ProcessBuilder;

class ProcessExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    protected $supportedStepTypes = array('process');
    protected $supportedActions = array('run');

    protected $defaultTimeout = 86400;

    /** @var ReferenceResolverBagInterface $referenceResolver */
    protected $referenceResolver;

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
            throw new \Exception("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new \Exception("Invalid step definition: value '$action' is not allowed for 'mode'");
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
            throw new \Exception("Can not run process: command missing");
        }

        $builder = new ProcessBuilder();

        // mandatory args and options
        $builderArgs = array($this->referenceResolver->resolveReference($dsl['command']));

        if (isset($dsl['arguments'])) {
            foreach($dsl['arguments'] as $arg) {
                $builderArgs[] = $this->referenceResolver->resolveReference($arg);
            }
        }

        $process = $builder
            ->setArguments($builderArgs)
            ->getProcess();

        // allow long migrations processes by default
        $timeout = $this->defaultTimeout;
        if (isset($dsl['timeout'])) {
            $timeout = $dsl['timeout'];
        }
        $process->setTimeout($timeout);

        if (isset($dsl['working_directory'])) {
            $process->setWorkingDirectory($dsl['working_directory']);
        }

        if (isset($dsl['disable_output'])) {
            $process->disableOutput();
        }

        if (isset($dsl['environment'])) {
            $process->setEnv($dsl['environment']);
        }

        $process->run();

        $this->setReferences($process, $dsl);

        return $process;
    }

    protected function setReferences(Process $process, $dsl)
    {
        if (!array_key_exists('references', $dsl)) {
            return false;
        }

        foreach ($dsl['references'] as $reference) {
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
                    throw new \InvalidArgumentException('Process executor does not support setting references for attribute ' . $reference['attribute']);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }
}

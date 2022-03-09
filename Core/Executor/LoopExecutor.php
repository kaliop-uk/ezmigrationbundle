<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\LoopBreakException;
use Kaliop\eZMigrationBundle\API\Exception\LoopContinueException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationStepSkippedException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\Core\MigrationService;
use Kaliop\eZMigrationBundle\Core\ReferenceResolver\LoopResolver;

class LoopExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    protected $supportedStepTypes = array('loop');
    protected $supportedActions = array('repeat', 'iterate', 'break', 'continue');

    /** @var MigrationService $migrationService */
    protected $migrationService;

    /** @var LoopResolver $loopResolver */
    protected $loopResolver;

    protected $referenceResolver;

    public function __construct($migrationService, $loopResolver, ReferenceResolverInterface $referenceResolver)
    {
        $this->migrationService = $migrationService;
        $this->loopResolver = $loopResolver;
        $this->referenceResolver = $referenceResolver;
    }

    /**
     * @param MigrationStep $step
     * @return mixed
     * @throws InvalidStepDefinitionException
     * @throws \Exception
     */
    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        // BC
        if (isset($step->dsl['repeat'])) {
            if (isset($step->dsl['over'])) {
                throw new InvalidStepDefinitionException("Invalid step definition: can not have both 'repeat' and 'over'");
            }
            if (isset($step->dsl['mode']) && $step->dsl['mode'] != 'repeat') {
                throw new InvalidStepDefinitionException("Invalid step definition: can not have different 'repeat' and 'mode'");
            }
            $action = 'repeat';
        } elseif (isset($step->dsl['over'])) {
            if (isset($step->dsl['mode']) && $step->dsl['mode'] != 'iterate') {
                throw new InvalidStepDefinitionException("Invalid step definition: can not have 'over' and 'mode' != 'iterate'");
            }
            $action = 'iterate';
        } elseif (isset($step->dsl['mode'])) {
            $action = $step->dsl['mode'];
        } else {
            throw new InvalidStepDefinitionException("Invalid step definition: missing 'repeat' or 'over' or 'mode'");
        }

        if (!in_array($action, $this->supportedActions)) {
            throw new InvalidStepDefinitionException("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        $this->skipStepIfNeeded($step);

        // can not use keywords as method names
        $action = 'loop' . ucfirst($action);

        return $this->$action($step->dsl, $step->context);
    }

    protected function loopRepeat($dsl, $context)
    {
        $repeat = $this->referenceResolver->resolveReference($dsl['repeat']);
        if ((!is_int($repeat) && !ctype_digit($repeat)) || $repeat < 0) {
            throw new InvalidStepDefinitionException("Invalid step definition: '$repeat' is not a positive integer");
        }

        $stepExecutors = $this->validateSteps($dsl);

        $this->loopResolver->beginLoop();
        $result = null;

        // NB: we are *not* firing events for each pass of the loop... it might be worth making that optionally happen ?
        for ($i = 0; $i < $repeat; $i++) {

            $this->loopResolver->loopStep();

            try {
                foreach ($dsl['steps'] as $j => $stepDef) {
                    $type = $stepDef['type'];
                    unset($stepDef['type']);
                    $subStep = new MigrationStep($type, $stepDef, array_merge($context, array()));
                    try {
                        $result = $stepExecutors[$j]->execute($subStep);
                    } catch(MigrationStepSkippedException $e) {
                        // all ok, continue the loop
                    } catch(LoopContinueException $e) {
                        // all ok, move to the next iteration
                        break;
                    }
                }
            } catch(LoopBreakException $e) {
                // all ok, exit the loop
                break;
            }
        }

        $this->loopResolver->endLoop();
        return $result;
    }

    protected function loopIterate($dsl, $context)
    {
        $stepExecutors = $this->validateSteps($dsl);

        $over = $this->referenceResolver->resolveReference($dsl['over']);

        $this->loopResolver->beginLoop();
        $result = null;

        foreach ($over as $key => $value) {
            $this->loopResolver->loopStep($key, $value);

            try {
                foreach ($dsl['steps'] as $j => $stepDef) {
                    $type = $stepDef['type'];
                    unset($stepDef['type']);
                    $subStep = new MigrationStep($type, $stepDef, array_merge($context, array()));
                    try {
                        $result = $stepExecutors[$j]->execute($subStep);
                    } catch(MigrationStepSkippedException $e) {
                        // all ok, continue the loop
                    } catch(LoopContinueException $e) {
                        // all ok, move to the next iteration
                        break;
                    }
                }
            } catch(LoopBreakException $e) {
                // all ok, exit the loop
                break;
            }
        }

        $this->loopResolver->endLoop();
        return $result;
    }

    /**
     * @param array $dsl
     * @param $context
     * @return void
     * @throws LoopBreakException
     */
    protected function loopBreak($dsl, $context)
    {
        $message = isset($dsl['message']) ? $dsl['message'] : '';

        throw new LoopBreakException($message);
    }

    /**
     * @param array $dsl
     * @param $context
     * @return void
     * @throws LoopContinueException
     */
    protected function loopContinue($dsl, $context)
    {
        $message = isset($dsl['message']) ? $dsl['message'] : '';

        throw new LoopContinueException($message);
    }

    protected function validateSteps($dsl)
    {
        if (!isset($dsl['steps']) || !is_array($dsl['steps'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: missing 'steps' or not an array");
        }

        // before engaging in the loop, check that all steps are valid
        $stepExecutors = array();

        foreach ($dsl['steps'] as $i => $stepDef) {
            $type = $stepDef['type'];
            try {
                $stepExecutors[$i] = $this->migrationService->getExecutor($type);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException($e->getMessage() . " in sub-step of a loop step");
            }
        }
        return $stepExecutors;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\Core\MigrationService;
use Kaliop\eZMigrationBundle\Core\ReferenceResolver\LoopResolver;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationStepSkippedException;

class LoopExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    protected $supportedStepTypes = array('loop');

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

        if (!isset($step->dsl['repeat']) && !isset($step->dsl['over'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: missing 'repeat' or 'over'");
        }

        if (isset($step->dsl['repeat']) && isset($step->dsl['over'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: can not have both 'repeat' and 'over'");
        }

        if (isset($step->dsl['repeat']) && $step->dsl['repeat'] < 0) {
            throw new InvalidStepDefinitionException("Invalid step definition: 'repeat' is not a positive integer");
        }

        if (!isset($step->dsl['steps']) || !is_array($step->dsl['steps'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: missing 'steps' or not an array");
        }

        $this->skipStepIfNeeded($step);

        // before engaging in the loop, check that all steps are valid
        $stepExecutors = array();
        foreach ($step->dsl['steps'] as $i => $stepDef) {
            $type = $stepDef['type'];
            try {
                $stepExecutors[$i] = $this->migrationService->getExecutor($type);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException($e->getMessage() . " in sub-step of a loop step");
            }
        }

        $this->loopResolver->beginLoop();
        $result = null;
        if (isset($step->dsl['over'])) {
            $over = $this->referenceResolver->resolveReference($step->dsl['over']);
            foreach ($over as $key => $value) {
                $this->loopResolver->loopStep($key, $value);

                foreach ($step->dsl['steps'] as $j => $stepDef) {
                    $type = $stepDef['type'];
                    unset($stepDef['type']);
                    $subStep = new MigrationStep($type, $stepDef, array_merge($step->context, array()));
                    try {
                        $result = $stepExecutors[$j]->execute($subStep);
                    } catch(MigrationStepSkippedException $e) {
                        // all ok, continue the loop
                    }
                }
            }
        } else {
            // NB: we are *not* firing events for each pass of the loop... it might be worth making that optionally happen ?
            for ($i = 0; $i < $step->dsl['repeat']; $i++) {

                $this->loopResolver->loopStep();

                foreach ($step->dsl['steps'] as $j => $stepDef) {
                    $type = $stepDef['type'];
                    unset($stepDef['type']);
                    $subStep = new MigrationStep($type, $stepDef, array_merge($step->context, array()));
                    try {
                        $result = $stepExecutors[$j]->execute($subStep);
                    } catch(MigrationStepSkippedException $e) {
                        // all ok, continue the loop
                    }
                }
            }
        }

        $this->loopResolver->endLoop();
        return $result;
    }
}

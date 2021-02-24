<?php

namespace Kaliop\eZMigrationBundle\Core\EventListener;

use Kaliop\eZMigrationBundle\API\Event\BeforeStepExecutionEvent;
use Kaliop\eZMigrationBundle\API\Event\StepExecutedEvent;
use Kaliop\eZMigrationBundle\API\Event\MigrationAbortedEvent;
use Kaliop\eZMigrationBundle\API\Event\MigrationSuspendedEvent;
use \Kaliop\eZMigrationBundle\API\Collection\AbstractCollection;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * A listener designed to give feedback on the execution of migration steps
 *
 * @todo add proper support for plural forms, as well as for proper sentences when dealing with empty collections
 */
class TracingStepExecutedListener
{
    /** @var  OutputInterface $output */
    protected $output;
    protected $minVerbosityLevel = OutputInterface::VERBOSITY_VERBOSE;
    protected $entity = 'migration';
    protected $enabled = true;
    protected $stepStartTime;
    protected $stepStartMemory;

    public function __construct($enabled = true)
    {
        $this->enabled = $enabled;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * NB: only works when using an OutputInterface to echo output
     * @param int $level
     */
    public function setMinVerbosity($level)
    {
        $this->minVerbosityLevel = $level;
    }

    public function enable()
    {
        $this->enabled = true;
    }

    public function disable()
    {
        $this->enabled = false;
    }

    public function onBeforeStepExecution(BeforeStepExecutionEvent $event) {
        if (!$this->enabled) {
            return;
        }

        // optimization - only valid because we only echo in this method at std levels and higher
        if ($this->output && $this->output->getVerbosity() < $this->minVerbosityLevel) {
            return;
        }

        if ($this->output && $this->output->isVeryVerbose()) {
            $this->stepStartTime = microtime(true);
            $this->stepStartMemory = memory_get_usage(true);
        }

        $type = $event->getStep()->type;
        $dsl = $event->getStep()->dsl;
        $context = $event->getStep()->context;
        $stepNr = '';
        if (isset($context['step'])) {
            $stepNr = "{$context['step']}: ";
        }
        if (isset($dsl['mode'])) {
            $type .= ' / ' . $dsl['mode'];
        }
        $out = $this->entity . " step $stepNr'$type' will be executed...";

        $this->echoMessage($out, OutputInterface::VERBOSITY_VERY_VERBOSE);
    }

    public function onStepExecuted(StepExecutedEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        // optimization - only valid because we only echo in this method at std levels and higher
        if ($this->output && $this->output->getVerbosity() < $this->minVerbosityLevel) {
            return;
        }

        if ($this->output && $this->output->isVeryVerbose()) {
            $stepTime = microtime(true) - $this->stepStartTime;
            $stepMemory = memory_get_usage(true) - $this->stepStartMemory;
        }

        $obj = $event->getResult();
        $type = $event->getStep()->type;
        $dsl = $event->getStep()->dsl;
        $context = $event->getStep()->context;
        $stepNr = '';
        if (isset($context['step'])) {
            $stepNr = "{$context['step']}: ";
        }

        switch ($type) {
            case 'trash':
                $type = 'trashed_item';
                // fall through voluntarily
            case 'content':
            case 'content_type':
            case 'content_type_group':
            case 'language':
            case 'location':
            case 'object_state':
            case 'object_state_group':
            case 'role':
            case 'section':
            case 'tag':
            case 'user':
            case 'user_group':
                $action = isset($dsl['mode']) ? ($dsl['mode'] == 'load' ? 'loaded' : ($dsl['mode'] . 'd')) : 'acted upon';
                $verb = 'has';
                $prefix = '';
                $label = '';
                if ($obj instanceof AbstractCollection || is_array($obj)) {
                    $count = count($obj);
                    if ($count == 0) {
                        $prefix = 'no ';
                    } else {
                        $label = ' ' . $this->getObjectIdentifierAsString($obj);
                        if (count($obj) > 1) {
                            $verb = 'have';
                        }
                    }
                }
                $out = $this->entity . " step {$stepNr}{$prefix}{$type}{$label} {$verb} been {$action}";
                break;
            case 'sql':
                $out = $this->entity . " step {$stepNr}sql has been executed";
                break;
            case 'php':
                $out = $this->entity . " step {$stepNr}class '{$dsl['class']}' has been executed";
                break;
            default:
                // custom migration step types...
                if (isset($dsl['mode'])) {
                    $type .= ' / ' . $dsl['mode'];
                }
                $out = $this->entity . " step $stepNr'$type' has been executed";
        }

        if ($this->output && $this->output->isVeryVerbose()) {
           $out .= sprintf(". <info>Time taken: %.3f secs, memory delta: %d bytes</info>", $stepTime, $stepMemory);
        }

        $this->echoMessage($out);
    }

    public function onMigrationAborted(MigrationAbortedEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        $type = $event->getStep()->type;
        $dsl = $event->getStep()->dsl;
        if (isset($dsl['mode'])) {
            $type .= '/' . $dsl['mode'];
        }

        $out = $this->entity . " aborted with status " . $event->getException()->getCode() . " during execution of step '$type'. Message: " . $event->getException()->getMessage();

        $this->echoMessage($out);
    }

    public function onMigrationSuspended(MigrationSuspendedEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        $type = $event->getStep()->type;
        $dsl = $event->getStep()->dsl;
        if (isset($dsl['mode'])) {
            $type .= '/' . $dsl['mode'];
        }

        $out = $this->entity . " suspended during execution of step '$type'. Message: " . $event->getException()->getMessage();

        $this->echoMessage($out);
    }

    protected function echoMessage($out, $verbosity = null)
    {
        if ($this->output) {
            if ($this->output->getVerbosity() >= ($verbosity ? $verbosity : $this->minVerbosityLevel)) {
                $this->output->writeln($out);
            }
        } else {
            echo $out . "\n";
        }
    }

    protected function getObjectIdentifierAsString($objOrCollection)
    {
        if ($objOrCollection instanceof AbstractCollection || is_array($objOrCollection)) {
            $out = array();
            if ($objOrCollection instanceof AbstractCollection) {
                $objOrCollection = $objOrCollection->getArrayCopy();
            }
            // totally arbitrary limit
            foreach (array_slice($objOrCollection, 0, 25) as $obj) {
                $out[] = $this->getObjectIdentifierAsString($obj);
            }
            if (count($objOrCollection) > 25) {
                $out[24] = 'etc...';
            }
            return implode(", ", $out);
        }

        switch (gettype($objOrCollection)) {

            case 'object':
                if ($objOrCollection instanceof \eZ\Publish\API\Repository\Values\Content\Content ||
                    $objOrCollection instanceof \eZ\Publish\API\Repository\Values\User\UserGroup
                ) {
                    return "'" . $objOrCollection->contentInfo->name . "'";
                }
                if ($objOrCollection instanceof \eZ\Publish\API\Repository\Values\ContentType\ContentType ||
                    $objOrCollection instanceof \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup ||
                    $objOrCollection instanceof \eZ\Publish\API\Repository\Values\ObjectState\ObjectState ||
                    $objOrCollection instanceof \eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroup ||
                    $objOrCollection instanceof \eZ\Publish\API\Repository\Values\User\Role ||
                    $objOrCollection instanceof \eZ\Publish\API\Repository\Values\Content\Section
                ) {
                    return "'" . $objOrCollection->identifier . "'";
                }
                if ($objOrCollection instanceof \eZ\Publish\API\Repository\Values\Content\Location ||
                    $objOrCollection instanceof \eZ\Publish\API\Repository\Values\Content\TrashItem) {
                    return "'" . $objOrCollection->pathString . "'";
                }
                if ($objOrCollection instanceof \eZ\Publish\API\Repository\Values\Content\Language) {
                    return "'" . $objOrCollection->languageCode . "'";
                }
                if ($objOrCollection instanceof \eZ\Publish\API\Repository\Values\User\User) {
                    return "'" . $objOrCollection->login . "'";
                }
                // unknown objects - we can't know what the desired identifier is...
                return '';

            default:
                // scalars: the identifier is the value...
                /// @todo make readable NULL, true/false
                return "'" . $objOrCollection . "'";
        }
    }
}

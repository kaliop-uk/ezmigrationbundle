<?php

namespace Kaliop\eZMigrationBundle\Core\EventListener;

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

    public function onStepExecuted(StepExecutedEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        $obj = $event->getResult();
        $type = $event->getStep()->type;
        $dsl = $event->getStep()->dsl;

        switch ($type) {
            case 'content':
            case 'content_type':
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
                $out = $type . ' ' . $this->getObjectIdentifierAsString($obj) . ' has been ' . $action;
                break;
            case 'sql':
                $out = 'sql has been executed';
                break;
            case 'php':
                $out = "class '{$dsl['class']}' has been executed";
                break;
            default:
                // custom migration step types...
                if (isset($dsl['mode'])) {
                    $type .= ' / ' . $dsl['mode'];
                }
                $out = $this->entity . " step '$type' has been executed";
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

    protected function echoMessage($out)
    {
        if ($this->output) {
            if ($this->output->getVerbosity() >= $this->minVerbosityLevel) {
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
            foreach ($objOrCollection as $obj) {
                $out[] = $this->getObjectIdentifierAsString($obj);
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
                if ($objOrCollection instanceof \eZ\Publish\API\Repository\Values\Content\Location) {
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

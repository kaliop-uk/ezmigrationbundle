<?php

namespace Kaliop\eZMigrationBundle\Core\EventListener;

use Kaliop\eZMigrationBundle\API\Event\StepExecutedEvent;
use \Kaliop\eZMigrationBundle\API\Collection\AbstractCollection;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * A listener designed to give feedback on the execution of migration steps
 *
 * @todo qdd proper support for plural forms, as well as for proper sentences when dealing with empty collections
 */
class TracingStepExecutedListener
{
    protected $output;
    protected $minVerbosityLevel = OutputInterface::VERBOSITY_VERBOSE;

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

    public function onStepExecuted(StepExecutedEvent $event)
    {
        $obj = $event->getResult();
        $type = $event->getStep()->type;
        $dsl = $event->getStep()->dsl;
        $action = isset($dsl['mode']) ? ($dsl['mode'] . 'd') : 'acted upon';

        switch ($type) {
            case 'content':
            case 'content_type':
            case 'language':
            case 'location':
            case 'object_state':
            case 'object_state_group':
            case 'role':
            case 'tag':
            case 'user':
            case 'user_group':
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
                $out = "migration step '$type' has been executed";
        }

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

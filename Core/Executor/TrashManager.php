<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\TrashedItemCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\TrashMatcher;

/**
 * Handles trash migrations.
 */
class TrashManager extends RepositoryExecutor
{
    protected $supportedActions = array('purge', 'recover', 'delete');
    protected $supportedStepTypes = array('trash');

    /** @var TrashMatcher $trashMatcher */
    protected $trashMatcher;

    /**
     * @param TrashMatcher $trashMatcher
     */
    public function __construct(TrashMatcher $trashMatcher)
    {
        $this->trashMatcher = $trashMatcher;
    }

    /**
     * Handles emptying the trash
     */
    protected function purge($step)
    {
        $trashService = $this->repository->getTrashService();

        $trashService->emptyTrash();

        return true;
    }

    /**
     * Handles the trash-restore migration action
     */
    protected function recover($step)
    {
        $itemsCollection = $this->matchItems('restore', $step);

        if (count($itemsCollection) > 1 && array_key_exists('references', $step->dsl)) {
            throw new \Exception("Can not execute Trash restore because multiple types match, and a references section is specified in the dsl. References can be set when only 1 section matches");
        }

        $trashService = $this->repository->getTrashService();
        foreach ($itemsCollection as $key => $item) {
            /// @todo support handling of custom restoration locations
            $trashService->recover($item);
        }

        $this->setReferences($itemsCollection, $step);

        return $itemsCollection;
    }

    /**
     * Handles the trash-restore migration action
     */
    protected function delete($step)
    {
        $itemsCollection = $this->matchItems('delete', $step);

        if (count($itemsCollection) > 1 && array_key_exists('references', $step->dsl)) {
            throw new \Exception("Can not execute Trash restore because multiple types match, and a references section is specified in the dsl. References can be set when only 1 section matches");
        }

        $this->setReferences($itemsCollection, $step);

        $trashService = $this->repository->getTrashService();
        foreach ($itemsCollection as $key => $item) {
            $trashService->deleteTrashItem($item);
        }

        $this->setReferences($itemsCollection, $step);

        return $itemsCollection;
    }

    /**
     * @param string $action
     * @return TrashedItemCollection
     * @throws \Exception
     */
    protected function matchItems($action, $step)
    {
        if (!isset($step->dsl['match'])) {
            throw new \Exception("A match condition is required to $action trash items");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        return $this->trashMatcher->match($match);
    }

    /**
     * Sets references to certain trashed-item attributes.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\TrashItem|TrashedItemCollection $item
     * @param $step
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute
     * @return boolean
     */
    protected function setReferences($item, $step)
    {
        if (!array_key_exists('references', $step->dsl)) {
            return false;
        }

        $references = $this->setReferencesCommon($item, $step->dsl['references']);
        $item = $this->insureSingleEntity($item, $references);

        foreach ($references as $reference) {
            switch ($reference['attribute']) {
                /// @todo a trashed item extends a location, so in theory everything 'location' here should work
                /*case 'section_id':
                case 'id':
                    $value = $section->id;
                    break;
                case 'section_identifier':
                case 'identifier':
                    $value = $section->identifier;
                    break;
                case 'section_name':
                case 'name':
                    $value = $section->name;
                    break;*/
                default:
                    throw new \InvalidArgumentException('Trash Manager does not support setting references for attribute ' . $reference['attribute']);
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

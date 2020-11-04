<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\LocationCollection;
use Kaliop\eZMigrationBundle\API\Collection\TrashedItemCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\Core\Matcher\TrashMatcher;
use Kaliop\eZMigrationBundle\Core\Helper\SortConverter;

/**
 * Handles trash migrations.
 */
class TrashManager extends RepositoryExecutor
{
    protected $supportedActions = array('purge', 'recover', 'load', 'delete');
    protected $supportedStepTypes = array('trash');

    /** @var TrashMatcher $trashMatcher */
    protected $trashMatcher;

    protected $sortConverter;

    /**
     * @param TrashMatcher $trashMatcher
     */
    public function __construct(TrashMatcher $trashMatcher, SortConverter $sortConverter)
    {
        $this->trashMatcher = $trashMatcher;
        $this->sortConverter = $sortConverter;
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
     *
     * @todo support handling of restoration to custom locations
     */
    protected function recover($step)
    {
        $itemsCollection = $this->matchItems('restore', $step);

        $this->validateResultsCount($itemsCollection, $step);

        $locations = array();
        $trashService = $this->repository->getTrashService();
        foreach ($itemsCollection as $key => $item) {
            $locations[] = $trashService->recover($item);
        }

        $this->setReferences(new LocationCollection($locations), $step);

        return $itemsCollection;
    }

    protected function load($step)
    {
        $itemsCollection = $this->matchItems('load', $step);

        $this->validateResultsCount($itemsCollection, $step);

        $this->setReferences($itemsCollection, $step);

        return $itemsCollection;
    }

    /**
     * Handles the trash-delete migration action
     */
    protected function delete($step)
    {
        $itemsCollection = $this->matchItems('delete', $step);

        $this->validateResultsCount($itemsCollection, $step);

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
            throw new InvalidStepDefinitionException("A match condition is required to $action trash items");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        return $this->trashMatcher->match($match);
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\Content\TrashItem|\eZ\Publish\API\Repository\Values\Content\Location $item
     * @param array $references the definitions of the references to set
     * @param $step
     * @throws InvalidStepDefinitionException
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($item, array $references, $step)
    {
        $refs = array();

        foreach ($references as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch ($reference['attribute']) {
                // a trashed item extends a location, so in theory everything 'location' here should work
                case 'location_id':
                case 'id':
                    $value = $item->id;
                    break;
                case 'remote_id':
                case 'location_remote_id':
                    $value = $item->remoteId;
                    break;
                case 'always_available':
                    $value = $item->contentInfo->alwaysAvailable;
                    break;
                case 'content_id':
                    $value = $item->contentId;
                    break;
                case 'content_type_id':
                    $value = $item->contentInfo->contentTypeId;
                    break;
                case 'content_type_identifier':
                    $contentTypeService = $this->repository->getContentTypeService();
                    $value = $contentTypeService->loadContentType($item->contentInfo->contentTypeId)->identifier;
                    break;
                case 'current_version':
                case 'current_version_no':
                    $value = $item->contentInfo->currentVersionNo;
                    break;
                case 'depth':
                    $value = $item->depth;
                    break;
                case 'is_hidden':
                    $value = $item->hidden;
                    break;
                case 'main_location_id':
                    $value = $item->contentInfo->mainLocationId;
                    break;
                case 'main_language_code':
                    $value = $item->contentInfo->mainLanguageCode;
                    break;
                case 'modification_date':
                    $value = $item->contentInfo->modificationDate->getTimestamp();
                    break;
                case 'name':
                    $value = $item->contentInfo->name;
                    break;
                case 'owner_id':
                    $value = $item->contentInfo->ownerId;
                    break;
                case 'parent_location_id':
                    $value = $item->parentLocationId;
                    break;
                case 'path':
                    $value = $item->pathString;
                    break;
                case 'priority':
                    $value = $item->priority;
                    break;
                case 'publication_date':
                    $value = $item->contentInfo->publishedDate->getTimestamp();
                    break;
                case 'section_id':
                    $value = $item->contentInfo->sectionId;
                    break;
                case 'section_identifier':
                    $sectionService = $this->repository->getSectionService();
                    $value = $sectionService->loadSection($item->contentInfo->sectionId)->identifier;
                    break;
                case 'sort_field':
                    $value = $this->sortConverter->sortField2Hash($item->sortField);
                    break;
                case 'sort_order':
                    $value = $this->sortConverter->sortOrder2Hash($item->sortOrder);
                    break;
                default:
                    throw new InvalidStepDefinitionException('Trash Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }
}

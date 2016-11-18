<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use Kaliop\eZMigrationBundle\API\Collection\ContentTypeGroupCollection;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;

class ContentTypeGroupMatcher extends AbstractMatcher
{
    const MATCH_CONTENTTYPEGROUP_ID = 'contenttypegroup_id';
    const MATCH_CONTENTTYPEGROUP_IDENTIFIER = 'contenttypegroup_identifier';

    protected $allowedConditions = array(
        self::MATCH_CONTENTTYPEGROUP_ID, self::MATCH_CONTENTTYPEGROUP_IDENTIFIER,
        // aliases
        'id', 'identifier'
    );
    protected $returns = 'ContentTypeGroup';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ContentTypeGroupCollection
     */
    public function match(array $conditions)
    {
        return $this->matchContentTypeGroup($conditions);
    }

    /**
     * @param int $groupId
     * @return ContentTypeGroup
     */
    public function matchByKey($groupId)
    {
        return $this->findContentTypeGroupsById([$groupId])[$groupId];
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ContentTypeGroupCollection
     */
    public function matchContentTypeGroup($conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_CONTENTTYPEGROUP_ID:
                    return new ContentTypeGroupCollection($this->findContentTypeGroupsById($values));

                case 'identifier':
                case self::MATCH_CONTENTTYPEGROUP_IDENTIFIER:
                    return new ContentTypeGroupCollection($this->findContentTypeGroupsByIdentifier($values));
            }
        }
    }

    /**
     * @param int[] $contentTypeGroupIds
     * @return ContentTypeGroup[]
     */
    protected function findContentTypeGroupsById(array $contentTypeGroupIds)
    {
        $contentTypeGroups = [];

        foreach ($contentTypeGroupIds as $contentTypeGroupId) {
            // return unique contents
            $contentTypeGroup = $this->repository->getContentTypeService()->loadContentTypeGroup($contentTypeGroupId);
            $contentTypeGroups[$contentTypeGroup->id] = $contentTypeGroup;
        }

        return $contentTypeGroups;
    }

        /**
         * @param int[] $contentTypeGroupIdentifiers
         * @return ContentTypeGroup[]
         */
    protected function findContentTypeGroupsByIdentifier(array $contentTypeGroupIdentifiers)
    {
        $contentTypeGroups = [];

        foreach ($contentTypeGroupIdentifiers as $contentTypeGroupIdentifier) {
            // return unique contents
            $contentTypeGroup = $this->repository->getContentTypeService()->loadContentTypeGroupByIdentifier($contentTypeGroupIdentifier);
            $contentTypeGroups[$contentTypeGroup->id] = $contentTypeGroup;
        }

        return $contentTypeGroups;
    }
}

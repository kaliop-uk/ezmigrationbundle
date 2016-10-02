<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;
use Kaliop\eZMigrationBundle\API\Collection\ContentTypeGroupCollection;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

class ContentTypeGroupMatcher extends AbstractMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

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
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ContentTypeGroupCollection
     */
    public function matchContentTypeGroup(array $conditions)
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

    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_CONTENTTYPEGROUP_ID => $key);
        }
        return array(self::MATCH_CONTENTTYPEGROUP_IDENTIFIER => $key);
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
     * @param string[] $contentTypeGroupIdentifiers
     * @return ContentTypeGroup[]
     */
    protected function findContentTypeGroupsByIdentifier(array $contentTypeGroupIdentifiers)
    {
        $contentTypeGroups = [];

        foreach ($contentTypeGroupIdentifiers as $contentTypeGroupIdentifier) {
            // return unique contents
            $contentTypeGroup = $this->repository->getContentTypeService()->loadContentTypeGroupByIdentifier($contentTypeGroupIdentifier);
            $roles[$contentTypeGroup->id] = $contentTypeGroup;
        }

        return $contentTypeGroups;
    }
}

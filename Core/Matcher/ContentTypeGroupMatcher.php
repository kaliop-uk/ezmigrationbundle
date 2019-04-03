<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;
use Kaliop\eZMigrationBundle\API\Collection\ContentTypeGroupCollection;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;

class ContentTypeGroupMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_CONTENTTYPEGROUP_ID = 'contenttypegroup_id';
    const MATCH_CONTENTTYPEGROUP_IDENTIFIER = 'contenttypegroup_identifier';

    protected $allowedConditions = array(
        self::MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_CONTENTTYPEGROUP_ID, self::MATCH_CONTENTTYPEGROUP_IDENTIFIER,
        // aliases
        'id', 'identifier'
    );
    protected $returns = 'ContentTypeGroup';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ContentTypeGroupCollection
     * @throws InvalidMatchConditionsException
     */
    public function match(array $conditions)
    {
        return $this->matchContentTypeGroup($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ContentTypeGroupCollection
     * @throws InvalidMatchConditionsException
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

                case self::MATCH_ALL:
                    return new ContentTypeGroupCollection($this->findAllContentTypeGroups());

                case self::MATCH_AND:
                    return $this->matchAnd($values);

                case self::MATCH_OR:
                    return $this->matchOr($values);

                case self::MATCH_NOT:
                    return new ContentTypeGroupCollection(array_diff_key($this->findAllContentTypeGroups(), $this->matchContentTypeGroup($values)->getArrayCopy()));

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
            $contentTypeGroups[$contentTypeGroup->id] = $contentTypeGroup;
        }

        return $contentTypeGroups;
    }

    /**
     * @return ContentTypeGroup[]
     */
    protected function findAllContentTypeGroups()
    {
        $contentTypeGroups = [];
        foreach ($this->repository->getContentTypeService()->loadContentTypeGroups() as $contentTypeGroup) {
            $contentTypeGroups[$contentTypeGroup->id] = $contentTypeGroup;
        }

        return $contentTypeGroups;
    }
}

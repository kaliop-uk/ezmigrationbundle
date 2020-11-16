<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;
use Kaliop\eZMigrationBundle\API\Collection\ContentTypeGroupCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

class ContentTypeGroupMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_CONTENTTYPEGROUP_ID = 'content_type_group_id';
    const MATCH_CONTENTTYPEGROUP_IDENTIFIER = 'content_type_group_identifier';

    protected $allowedConditions = array(
        self::MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_CONTENTTYPEGROUP_ID, self::MATCH_CONTENTTYPEGROUP_IDENTIFIER,
        // aliases
        'id', 'identifier',
        // BC
        'contenttypegroup_id', 'contenttypegroup_identifier',
    );
    protected $returns = 'ContentTypeGroup';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return ContentTypeGroupCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function match(array $conditions, $tolerateMisses = false)
    {
        return $this->matchContentTypeGroup($conditions, $tolerateMisses);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ContentTypeGroupCollection
     * @param bool $tolerateMisses
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function matchContentTypeGroup(array $conditions, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case 'contenttypegroup_id';
                case self::MATCH_CONTENTTYPEGROUP_ID:
                    return new ContentTypeGroupCollection($this->findContentTypeGroupsById($values, $tolerateMisses));

                case 'identifier':
                case 'contenttypegroup_identifier':
                case self::MATCH_CONTENTTYPEGROUP_IDENTIFIER:
                    return new ContentTypeGroupCollection($this->findContentTypeGroupsByIdentifier($values, $tolerateMisses));

                case self::MATCH_ALL:
                    return new ContentTypeGroupCollection($this->findAllContentTypeGroups());

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses);

                case self::MATCH_NOT:
                    return new ContentTypeGroupCollection(array_diff_key($this->findAllContentTypeGroups(), $this->matchContentTypeGroup($values, true)->getArrayCopy()));

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
     * @param bool $tolerateMisses
     * @return ContentTypeGroup[]
     * @throws NotFoundException
     */
    protected function findContentTypeGroupsById(array $contentTypeGroupIds, $tolerateMisses = false)
    {
        $contentTypeGroups = [];

        foreach ($contentTypeGroupIds as $contentTypeGroupId) {
            try {
                // return unique contents
                $contentTypeGroup = $this->repository->getContentTypeService()->loadContentTypeGroup($contentTypeGroupId);
                $contentTypeGroups[$contentTypeGroup->id] = $contentTypeGroup;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $contentTypeGroups;
    }

    /**
     * @param string[] $contentTypeGroupIdentifiers
     * @param bool $tolerateMisses
     * @return ContentTypeGroup[]
     * @throws NotFoundException
     */
    protected function findContentTypeGroupsByIdentifier(array $contentTypeGroupIdentifiers, $tolerateMisses = false)
    {
        $contentTypeGroups = [];

        foreach ($contentTypeGroupIdentifiers as $contentTypeGroupIdentifier) {
            try {
                // return unique contents
                $contentTypeGroup = $this->repository->getContentTypeService()->loadContentTypeGroupByIdentifier($contentTypeGroupIdentifier);
                $contentTypeGroups[$contentTypeGroup->id] = $contentTypeGroup;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
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

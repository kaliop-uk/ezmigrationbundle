<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use Kaliop\eZMigrationBundle\API\Collection\ContentTypeCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

/**
 * Note: disallowing matches by remote_id allows us to implement KeyMatcherInterface without the risk of users getting
 * confused as to what they are matching...
 */
class ContentTypeMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_CONTENTTYPE_ID = 'content_type_id';
    const MATCH_CONTENTTYPE_IDENTIFIER = 'content_type_identifier';
    //const MATCH_CONTENTTYPE_REMOTE_ID = 'content_type_remote_id';

    protected $allowedConditions = array(
        self::MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_CONTENTTYPE_ID, self::MATCH_CONTENTTYPE_IDENTIFIER, //self::MATCH_CONTENTTYPE_REMOTE_ID,
        // aliases
        'id', 'identifier', // 'remote_id'
        // BC
        'contenttype_id', 'contenttype_identifier',
    );
    protected $returns = 'ContentType';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return ContentTypeCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function match(array $conditions, $tolerateMisses = false)
    {
        return $this->matchContentType($conditions, $tolerateMisses);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return ContentTypeCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function matchContentType(array $conditions, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case 'contenttype_id':
                case self::MATCH_CONTENTTYPE_ID:
                   return new ContentTypeCollection($this->findContentTypesById($values, $tolerateMisses));

                case 'identifier':
                case 'contenttype_identifier':
                case self::MATCH_CONTENTTYPE_IDENTIFIER:
                    return new ContentTypeCollection($this->findContentTypesByIdentifier($values, $tolerateMisses));

                /*case 'remote_id':
                case self::MATCH_CONTENTTYPE_REMOTE_ID:
                    return new ContentTypeCollection($this->findContentTypesByRemoteId($values));*/

                case self::MATCH_ALL:
                    return new ContentTypeCollection($this->findAllContentTypes());

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses);

                case self::MATCH_NOT:
                    return new ContentTypeCollection(array_diff_key($this->findAllContentTypes(), $this->matchContentType($values, true)->getArrayCopy()));
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_CONTENTTYPE_ID => $key);
        }
        return array(self::MATCH_CONTENTTYPE_IDENTIFIER => $key);
    }

    /**
     * @param int[] $contentTypeIds
     * @param bool $tolerateMisses
     * @return ContentType[]
     * @throws NotFoundException
     */
    protected function findContentTypesById(array $contentTypeIds, $tolerateMisses = false)
    {
        $contentTypes = [];

        foreach ($contentTypeIds as $contentTypeId) {
            try {
                // return unique contents
                $contentType = $this->repository->getContentTypeService()->loadContentType($contentTypeId);
                $contentTypes[$contentType->id] = $contentType;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $contentTypes;
    }

    /**
     * @param string[] $contentTypeIdentifiers
     * @param bool $tolerateMisses
     * @return ContentType[]
     * @throws NotFoundException
     */
    protected function findContentTypesByIdentifier(array $contentTypeIdentifiers, $tolerateMisses = false)
    {
        $contentTypes = [];

        foreach ($contentTypeIdentifiers as $contentTypeIdentifier) {
            try {
                // return unique contents
                $contentType = $this->repository->getContentTypeService()->loadContentTypeByIdentifier($contentTypeIdentifier);
                $contentTypes[$contentType->id] = $contentType;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $contentTypes;
    }

    /**
     * @param int[] $contentTypeRemoteIds
     * @param bool $tolerateMisses
     * @return ContentType[]
     */
    protected function findContentTypesByRemoteId(array $contentTypeRemoteIds, $tolerateMisses = false)
    {
        $contentTypes = [];

        foreach ($contentTypeRemoteIds as $contentTypeRemoteId) {
            try {
                // return unique contents
                $contentType = $this->repository->getContentTypeService()->loadContentTypeByRemoteId($contentTypeRemoteId);
                $contentTypes[$contentType->id] = $contentType;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $contentTypes;
    }

    /**
     * @return ContentType[]
     */
    protected function findAllContentTypes()
    {
        $contentTypes = [];

        $contentTypeService = $this->repository->getContentTypeService();
        foreach ($contentTypeService->loadContentTypeGroups() as $contentTypeGroup) {
            foreach ($contentTypeService->loadContentTypes($contentTypeGroup) as $contentType) {
                $contentTypes[$contentType->id] = $contentType;
            }
        }

        return $contentTypes;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use Kaliop\eZMigrationBundle\API\Collection\ContentTypeCollection;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

/**
 * Note: disallowing matches by remote_id allows us to implement KeyMatcherInterface without the risk of users getting
 * confused as to what they are matching...
 */
class ContentTypeMatcher extends AbstractMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_CONTENTTYPE_ID = 'contenttype_id';
    const MATCH_CONTENTTYPE_IDENTIFIER = 'contenttype_identifier';
    //const MATCH_CONTENTTYPE_REMOTE_ID = 'contenttype_remote_id';

    protected $allowedConditions = array(
        self::MATCH_CONTENTTYPE_ID, self::MATCH_CONTENTTYPE_IDENTIFIER, //self::MATCH_CONTENTTYPE_REMOTE_ID,
        // aliases
        'id', 'identifier', // 'remote_id'
    );
    protected $returns = 'ContentType';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ContentTypeCollection
     */
    public function match(array $conditions)
    {
        return $this->matchContentType($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ContentTypeCollection
     */
    public function matchContentType(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_CONTENTTYPE_ID:
                   return new ContentTypeCollection($this->findContentTypesById($values));

                case 'identifier':
                case self::MATCH_CONTENTTYPE_IDENTIFIER:
                    return new ContentTypeCollection($this->findContentTypesByIdentifier($values));

                /*case 'remote_id':
                case self::MATCH_CONTENTTYPE_REMOTE_ID:
                    return new ContentTypeCollection($this->findContentTypesByRemoteId($values));*/
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
     * @return ContentType[]
     */
    protected function findContentTypesById(array $contentTypeIds)
    {
        $contentTypes = [];

        foreach ($contentTypeIds as $contentTypeId) {
            // return unique contents
            $contentType = $this->repository->getContentTypeService()->loadContentType($contentTypeId);
            $contentTypes[$contentType->id] = $contentType;
        }

        return $contentTypes;
    }

    /**
     * @param string[] $contentTypeIdentifiers
     * @return ContentType[]
     */
    protected function findContentTypesByIdentifier(array $contentTypeIdentifiers)
    {
        $contentTypes = [];

        foreach ($contentTypeIdentifiers as $contentTypeIdentifier) {
            // return unique contents
            $contentType = $this->repository->getContentTypeService()->loadContentTypeByIdentifier($contentTypeIdentifier);
            $contentTypes[$contentType->id] = $contentType;
        }

        return $contentTypes;
    }

    /**
     * @param int[] $contentTypeRemoteIds
     * @return ContentType[]
     */
    protected function findContentTypesByRemoteId(array $contentTypeRemoteIds)
    {
        $contentTypes = [];

        foreach ($contentTypeRemoteIds as $contentTypeRemoteId) {
            // return unique contents
            $contentType = $this->repository->getContentTypeService()->loadContentTypeByRemoteId($contentTypeRemoteId);
            $contentTypes[$contentType->id] = $contentType;
        }

        return $contentTypes;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use Kaliop\eZMigrationBundle\API\Collection\TagCollection;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;

/**
 * @todo when matching by keyword, allow to pick the desired language
 */
class TagMatcher extends AbstractMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_TAG_ID = 'tag_id';
    const MATCH_TAG_REMOTE_ID = 'tag_remote_id';
    const MATCH_TAG_KEYWORD = 'tag_keyword';
    const MATCH_PARENT_TAG_ID = 'parent_tag_id';

    protected $allowedConditions = array(
        self::MATCH_AND, self::MATCH_OR,
        self::MATCH_TAG_ID, self::MATCH_TAG_REMOTE_ID, self::MATCH_TAG_KEYWORD, self::MATCH_PARENT_TAG_ID,
        // aliases
        'id', 'remote_id', 'keyword'
    );
    protected $returns = 'Tag';

    protected $translationHelper;
    protected $tagService;

    /**
     * @param $translationHelper
     * @param \Netgen\TagsBundle\API\Repository\TagsService $tagService
     */
    public function __construct($translationHelper, $tagService = null)
    {
        $this->translationHelper = $translationHelper;
        $this->tagService = $tagService;
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return TagCollection
     * @throws InvalidMatchConditionsException
     * @throws \Exception if TagBundle is missing
     */
    public function match(array $conditions)
    {
        return $this->matchTag($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return TagCollection
     * @throws InvalidMatchConditionsException
     * @throws \Exception if TagBundle is missing
     */
    public function matchTag(array $conditions)
    {
        if ($this->tagService == null) {
            throw new \Exception('Netgen TAG Bundle is required to use tag matching');
        }

        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_TAG_ID:
                   return new TagCollection($this->findTagsByIds($values));

                case 'remote_id':
                case self::MATCH_TAG_REMOTE_ID:
                    return new TagCollection($this->findTagsByRemoteIds($values));

                case 'keyword':
                case self::MATCH_TAG_KEYWORD:
                    return new TagCollection($this->findTagsByKeywords($values));

                case self::MATCH_PARENT_TAG_ID:
                    return new TagCollection($this->findTagsByParentTagIds($values));

                case self::MATCH_AND:
                    return $this->matchAnd($values);

                case self::MATCH_OR:
                    return $this->matchOr($values);
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_TAG_ID => $key);
        }

        throw new InvalidMatchConditionsException("Tag matcher can not uniquely identify the type of key used to match: " . $key);
    }

    /**
     * @param int[] $tagIds
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     */
    protected function findTagsByIds(array $tagIds)
    {
        $tags = [];

        foreach ($tagIds as $tagId) {
            // return unique contents
            $tag = $this->tagService->loadTag($tagId);
            $tags[$tag->id] = $tag;
        }

        return $tags;
    }

    protected function findTagsByParentTagIds(array $tagIds)
    {
        $tags = [];

        foreach ($tagIds as $tagId) {
            // return unique contents
            $tag = $this->tagService->loadTag($tagId);
            foreach ($this->tagService->loadTagChildren($tag) as $childTag) {
                $tags[$childTag->id] = $childTag;
            }
        }

        return $tags;
    }

    /**
     * @param string[] $tagRemoteIds
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     */
    protected function findTagsByRemoteIds(array $tagRemoteIds)
    {
        $tags = [];

        foreach ($tagRemoteIds as $tagRemoteId) {
            // return unique contents
            $tag = $this->tagService->loadTagByRemoteId($tagRemoteId);
            $tags[$tag->id] = $tag;
        }

        return $tags;
    }

    /**
     * @param string[] $tagKeywords
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     */
    protected function findTagsByKeywords(array $tagKeywords)
    {
        $tags = [];

        $availableLanguages = $this->translationHelper->getAvailableLanguages();

        foreach ($tagKeywords as $tagKeyword) {
            // return unique contents
            foreach ($this->tagService->loadTagsByKeyword($tagKeyword, $availableLanguages[0]) as $tag) {
                $tags[$tag->id] = $tag;
            }
        }

        return $tags;
    }
}

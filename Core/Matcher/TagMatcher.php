<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use Kaliop\eZMigrationBundle\API\Collection\TagCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

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
    const MATCH_PARENT_TAG_REMOTE_ID = 'parent_tag_remote_id';

    protected $allowedConditions = array(
        self::MATCH_AND, self::MATCH_OR, self::MATCH_ALL,
        self::MATCH_TAG_ID, self::MATCH_TAG_REMOTE_ID, self::MATCH_TAG_KEYWORD, self::MATCH_PARENT_TAG_ID, self::MATCH_PARENT_TAG_REMOTE_ID,
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
     * @param bool $tolerateMisses
     * @return TagCollection
     * @throws InvalidMatchConditionsException
     * @throws \Exception if TagBundle is missing
     */
    public function match(array $conditions, $tolerateMisses = false)
    {
        return $this->matchTag($conditions, $tolerateMisses);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return TagCollection
     * @throws InvalidMatchConditionsException
     * @throws \Exception if TagBundle is missing
     */
    public function matchTag(array $conditions, $tolerateMisses = false)
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
                   return new TagCollection($this->findTagsByIds($values, $tolerateMisses));

                case 'remote_id':
                case self::MATCH_TAG_REMOTE_ID:
                    return new TagCollection($this->findTagsByRemoteIds($values, $tolerateMisses));

                case 'keyword':
                case self::MATCH_TAG_KEYWORD:
                    return new TagCollection($this->findTagsByKeywords($values));

                case self::MATCH_PARENT_TAG_ID:
                    return new TagCollection($this->findTagsByParentTagIds($values, $tolerateMisses));

                case self::MATCH_PARENT_TAG_REMOTE_ID:
                    return new TagCollection($this->findTagsByParentTagRemoteIds($values, $tolerateMisses));

                case self::MATCH_ALL:
                    return new TagCollection($this->findAllTags());

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses);
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_TAG_ID => $key);
        }

        return array(self::MATCH_TAG_REMOTE_ID => $key);
    }

    /**
     * @param int[] $tagIds
     * @param bool $tolerateMisses
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    protected function findTagsByIds(array $tagIds, $tolerateMisses = false)
    {
        $tags = [];

        foreach ($tagIds as $tagId) {
            try {
                // return unique contents
                $tag = $this->tagService->loadTag($tagId);
                $tags[$tag->id] = $tag;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $tags;
    }

    /**
     * @param array $tagIds
     * @param false $tolerateMisses
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    protected function findTagsByParentTagIds(array $tagIds, $tolerateMisses = false)
    {
        $tags = [];

        foreach ($tagIds as $tagId) {
            try {
                $tag = $this->tagService->loadTag($tagId);

                // return unique contents
                foreach ($this->tagService->loadTagChildren($tag) as $childTag) {
                    $tags[$childTag->id] = $childTag;
                }
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $tags;
    }

    /**
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    protected function findAllTags()
    {
        $parentTags = [];

        foreach ($this->tagService->loadTagChildren() as $parentTag) {
            $parentTags[$parentTag->id] = $parentTag;
        }
        $tags = $parentTags;

        do {
            $childTags = $this->findTagsByParentTagIds(array_keys($parentTags));
            $tags = array_merge($tags, $childTags);
            $parentTags = $childTags;
        } while (count( $childTags ) > 0);

        return $tags;
    }

    /**
     * @param array $tagRemoteIds
     * @param false $tolerateMisses
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    protected function findTagsByParentTagRemoteIds(array $tagRemoteIds, $tolerateMisses = false)
    {
        return $this->findTagsByParentTagIds(array_keys($this->findTagsByRemoteIds($tagRemoteIds, $tolerateMisses)));
    }

    /**
     * @param string[] $tagRemoteIds
     * @param bool $tolerateMisses
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    protected function findTagsByRemoteIds(array $tagRemoteIds, $tolerateMisses = false)
    {
        $tags = [];

        foreach ($tagRemoteIds as $tagRemoteId) {
            try {
                // return unique contents
                $tag = $this->tagService->loadTagByRemoteId($tagRemoteId);
                $tags[$tag->id] = $tag;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $tags;
    }

    /**
     * NB: does NOT throw if no tags are matching...
     * @param string[] $tagKeywords
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     * @throws UnauthorizedException
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

<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\TagCollection;
use Kaliop\eZMigrationBundle\API\EnumerableMatcherInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\TagMatcher;
use Netgen\TagsBundle\API\Repository\Values\Tags\Tag;

/**
 * Handles tag migrations.
 */
class TagManager extends RepositoryExecutor implements MigrationGeneratorInterface, EnumerableMatcherInterface
{
    protected $supportedStepTypes = array('tag');
    protected $supportedActions = array('create', 'load', 'update', 'delete');

    /** @var \Netgen\TagsBundle\API\Repository\TagsService $tagService */
    protected $tagService;
    /** @var TagMatcher $tagMatcher */
    protected $tagMatcher;

    /**
     * @param TagMatcher $tagMatcher
     * @param \Netgen\TagsBundle\API\Repository\TagsService $tagService
     */
    public function __construct(TagMatcher $tagMatcher, $tagService = null)
    {
        $this->tagMatcher = $tagMatcher;
        $this->tagService = $tagService;
    }

    /**
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag
     * @throws \Exception
     * @todo if lang is not specified, use current language
     */
    protected function create($step)
    {
        $this->checkTagsBundleInstall();

        $alwaysAvail = isset($step->dsl['always_available']) ? $step->dsl['always_available'] : true;
        $parentTagId = 0;
        if (isset($step->dsl['parent_tag_id'])) {
            $parentTagId = $step->dsl['parent_tag_id'];
            $parentTagId = $this->resolveReference($parentTagId);
            // allow remote-ids to be used to reference parent tag
            if (!is_int($parentTagId) && !ctype_digit($parentTagId)) {
                $parentTag = $this->tagMatcher->matchOneByKey($parentTagId);
                $parentTagId = $parentTag->id;
            }
        }
        $remoteId = isset($step->dsl['remote_id']) ? $step->dsl['remote_id'] : null;

        if (isset($step->dsl['lang'])) {
            $lang = $step->dsl['lang'];
        } elseif (isset($step->dsl['main_language_code'])) {
            // deprecated tag
            $lang = $step->dsl['main_language_code'];
        } else {
            $lang = $this->getLanguageCode($step);
        }

        $tagCreateArray = array(
            'parentTagId' => $parentTagId,
            'mainLanguageCode' => $lang,
            'alwaysAvailable' => $alwaysAvail,
            'remoteId' => $remoteId
        );
        $tagCreateStruct = new \Netgen\TagsBundle\API\Repository\Values\Tags\TagCreateStruct($tagCreateArray);

        if (is_string($step->dsl['keywords'])) {
            $keyword = $this->resolveReference($step->dsl['keywords']);
            $tagCreateStruct->setKeyword($keyword);
        } else {
            foreach ($step->dsl['keywords'] as $langCode => $keyword)
            {
                $keyword = $this->resolveReference($keyword);
                $tagCreateStruct->setKeyword($keyword, $langCode);
            }
        }

        $tag = $this->tagService->createTag($tagCreateStruct);
        $this->setReferences($tag, $step);

        return $tag;
    }

    protected function load($step)
    {
        $this->checkTagsBundleInstall();

        $tagsCollection = $this->matchTags('load', $step);

        $this->validateResultsCount($tagsCollection, $step);

        $this->setReferences($tagsCollection, $step);

        return $tagsCollection;
    }

    protected function update($step)
    {
        $this->checkTagsBundleInstall();

        $tagsCollection = $this->matchTags('update', $step);

        $this->validateResultsCount($tagsCollection, $step);

        foreach ($tagsCollection as $key => $tag) {

            $alwaysAvail = isset($step->dsl['always_available']) ? $step->dsl['always_available'] : true;

            $remoteId = isset($step->dsl['remote_id']) ? $step->dsl['remote_id'] : null;

            if (isset($step->dsl['lang'])) {
                $lang = $step->dsl['lang'];
            } elseif (isset($step->dsl['main_language_code'])) {
                // deprecated tag
                $lang = $step->dsl['main_language_code'];
            } else {
                $lang = $this->getLanguageCode($step);
            }

            $tagUpdateArray = array(
                'mainLanguageCode' => $lang,
                'alwaysAvailable' => $alwaysAvail,
                'remoteId' => $remoteId
            );
            $tagUpdateStruct = new \Netgen\TagsBundle\API\Repository\Values\Tags\TagUpdateStruct($tagUpdateArray);

            if (is_string($step->dsl['keywords'])) {
                $keyword = $this->resolveReference($step->dsl['keywords']);
                $tagUpdateStruct->setKeyword($keyword);
            } else {
                foreach ($step->dsl['keywords'] as $langCode => $keyword) {
                    $keyword = $this->resolveReference($keyword);
                    $tagUpdateStruct->setKeyword($keyword, $langCode);
                }
            }

            $tag = $this->tagService->updateTag($tag, $tagUpdateStruct);

            $tagsCollection[$key] = $tag;
        }

        $this->setReferences($tagsCollection, $step);

        return $tagsCollection;
    }

    protected function delete($step)
    {
        $this->checkTagsBundleInstall();

        $tagsCollection = $this->matchTags('delete', $step);

        $this->validateResultsCount($tagsCollection, $step);

        $this->setReferences($tagsCollection, $step);

        // sort tags by depth so that there will be no errors in case we are deleting parent and child
        $tagsCollection = $tagsCollection->getArrayCopy();
        uasort($tagsCollection, function ($t1, $t2) {
            if ($t1->depth == $t2->depth) return 0;
            return ($t1->depth > $t2->depth) ? -1 : 1;
        });

        foreach ($tagsCollection as $tag) {
            $this->tagService->deleteTag($tag);
        }

        return $tagsCollection;
    }

    /**
     * @param string $action
     * @return TagCollection
     * @throws \Exception
     */
    protected function matchTags($action, $step)
    {
        if (!isset($step->dsl['match'])) {
            throw new InvalidStepDefinitionException("A match condition is required to $action a Tag");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        $tolerateMisses = isset($step->dsl['match_tolerate_misses']) ? $this->resolveReference($step->dsl['match_tolerate_misses']) : false;

        return $this->tagMatcher->match($match, $tolerateMisses);
    }

    /**
     * @param Tag $tag
     * @param array $references the definitions of the references to set
     * @throws InvalidStepDefinitionException
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($tag, array $references, $step)
    {
        $lang = $this->getLanguageCode($step);
        $refs = array();

        foreach ($references as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch ($reference['attribute']) {
                case 'id':
                case 'tag_id':
                    $value = $tag->id;
                    break;
                case 'always_available':
                    $value = $tag->alwaysAvailable;
                    break;
                case 'depth':
                    $value = $tag->depth;
                    break;
                case 'keyword':
                    $value = $tag->getKeyword($lang);
                    break;
                case 'main_language_code':
                    $value = $tag->mainLanguageCode;
                    break;
                case 'main_tag_id':
                    $value = $tag->mainTagId;
                    break;
                case 'modification_date':
                    $value = $tag->modificationDate->getTimestamp();
                    break;
                case 'path':
                    $value = $tag->pathString;
                    break;
                case 'parent_tag_id':
                    $value = $tag->parentTagId;
                    break;
                case 'remote_id':
                    $value = $tag->remoteId;
                    break;
                default:
                    throw new InvalidStepDefinitionException('Tag Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }

    /**
     * Generates a migration definition in array format
     *
     * @param array $matchConditions
     * @param string $mode
     * @param array $context
     * @return array Migration data
     * @throws InvalidMatchConditionsException
     * @throws \Exception
     */
    public function generateMigration(array $matchConditions, $mode, array $context = array())
    {
        $data = array();
        $previousUserId = $this->loginUser($this->getAdminUserIdentifierFromContext($context));
        try {
            $tagCollection = $this->tagMatcher->match($matchConditions);

            switch ($mode) {
                case 'create':
                    // sort top to bottom
                    $tagCollection = $tagCollection->getArrayCopy();
                    uasort($tagCollection, function ($t1, $t2) {
                        if ($t1->depth == $t2->depth) return 0;
                        return ($t1->depth > $t2->depth) ? 1 : -1;
                    });
                    break;
                case 'delete':
                    // sort bottom to top
                    $tagCollection = $tagCollection->getArrayCopy();
                    uasort($tagCollection, function ($t1, $t2) {
                        if ($t1->depth == $t2->depth) return 0;
                        return ($t1->depth > $t2->depth) ? -1 : 1;
                    });
                    break;
            }

            /** @var Tag $tag */
            foreach ($tagCollection as $tag) {

                $tagData = array(
                    'type' => reset($this->supportedStepTypes),
                    'mode' => $mode
                );

                switch ($mode) {
                    case 'create':
                        if ($tag->parentTagId != 0) {
                            $parentTagRid = $this->tagMatcher->matchOneByKey($tag->parentTagId)->remoteId;
                        } else {
                            $parentTagRid = 0;
                        }
                        $tagData = array_merge(
                            $tagData,
                            array(
                                'parent_tag_id' => $parentTagRid,
                                'always_available' => $tag->alwaysAvailable,
                                'lang' => $tag->mainLanguageCode,
                                'keywords' => $this->getTagKeywords($tag),
                                'remote_id' => $tag->remoteId
                            )
                        );
                        break;
                    case 'update':
                        $tagData = array_merge(
                            $tagData,
                            array(
                                'match' => array(
                                    TagMatcher::MATCH_TAG_REMOTE_ID => $tag->remoteId
                                ),
                                'always_available' => $tag->alwaysAvailable,
                                'lang' => $tag->mainLanguageCode,
                                'keywords' => $this->getTagKeywords($tag),
                            )
                        );
                        break;
                    case 'delete':
                        $tagData = array_merge(
                            $tagData,
                            array(
                                'match' => array(
                                    TagMatcher::MATCH_TAG_REMOTE_ID => $tag->remoteId
                                )
                            )
                        );
                        break;
                    default:
                        throw new InvalidStepDefinitionException("Executor 'tag' doesn't support mode '$mode'");
                }

                $data[] = $tagData;
            }

            $this->loginUser($previousUserId);
        } catch (\Exception $e) {
            $this->loginUser($previousUserId);
            throw $e;
        }

        return $data;
    }

    /**
     * @return string[]
     */
    public function listAllowedConditions()
    {
        return $this->tagMatcher->listAllowedConditions();
    }

    protected function getTagKeywords($tag)
    {
        $out = array();
        foreach ($tag->languageCodes as $languageCode) {
            $out[$languageCode] = $tag->getKeyword($languageCode);
        }
        return $out;
    }

    protected function checkTagsBundleInstall()
    {
        if (!$this->tagService)
        {
            throw new MigrationBundleException('To manipulate tags you must have NetGen Tags Bundle installed');
        }
    }
}

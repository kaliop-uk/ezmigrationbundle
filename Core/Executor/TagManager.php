<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Netgen\TagsBundle\API\Repository\Values\Tags\Tag;
use Kaliop\eZMigrationBundle\Core\Matcher\TagMatcher;
use Kaliop\eZMigrationBundle\API\Collection\TagCollection;

/**
 * Handles tag migrations.
 */
class TagManager extends RepositoryExecutor
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
            $parentTagId = $this->referenceResolver->resolveReference($parentTagId);
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
            $keyword = $this->referenceResolver->resolveReference($step->dsl['keywords']);
            $tagCreateStruct->setKeyword($keyword);
        } else {
            foreach ($step->dsl['keywords'] as $langCode => $keyword)
            {
                $keyword = $this->referenceResolver->resolveReference($keyword);
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

        $this->setReferences($tagsCollection, $step);

        return $tagsCollection;
    }

    protected function update($step)
    {
        $this->checkTagsBundleInstall();

        $tagsCollection = $this->matchTags('update', $step);

        if (count($tagsCollection) > 1 && array_key_exists('references', $step->dsl)) {
            throw new \Exception("Can not execute Tag update because multiple tags match, and a references section is specified in the dsl. References can be set when only 1 matches");
        }

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
                $keyword = $this->referenceResolver->resolveReference($step->dsl['keywords']);
                $tagUpdateStruct->setKeyword($keyword);
            } else {
                foreach ($step->dsl['keywords'] as $langCode => $keyword) {
                    $keyword = $this->referenceResolver->resolveReference($keyword);
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

        $this->setReferences($tagsCollection, $step);

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
            throw new \Exception("A match condition is required to $action a Tag");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        return $this->tagMatcher->match($match);
    }

    /**
     * @param Tag $tag
     * @param array $references the definitions of the references to set
     * @throws \InvalidArgumentException When trying to assign a reference to an unsupported attribute
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($tag, array $references, $step)
    {
        $lang = $this->getLanguageCode($step);
        $refs = array();

        foreach ($references as $reference) {
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
                    throw new \InvalidArgumentException('Tag Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }

    protected function checkTagsBundleInstall()
    {
        if (!$this->tagService)
        {
            throw new \Exception('To manipulate tags you must have NetGen Tags Bundle installed');
        }
    }
}

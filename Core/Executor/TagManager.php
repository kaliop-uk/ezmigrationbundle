<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

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
            throw new \Exception("The 'lang' key is required to create a tag.");
        }

        $tagCreateArray = array(
            'parentTagId' => $parentTagId,
            'mainLanguageCode' => $lang,
            'alwaysAvailable' => $alwaysAvail,
            'remoteId' => $remoteId
        );
        $tagCreateStruct = new \Netgen\TagsBundle\API\Repository\Values\Tags\TagCreateStruct($tagCreateArray);

        foreach ($step->dsl['keywords'] as $langCode => $keyword)
        {
            $tagCreateStruct->setKeyword($keyword, $langCode);
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
            throw new \Exception("Can not execute Tag update because multiple types match, and a references section is specified in the dsl. References can be set when only 1 matches");
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
                $lang = null;
            }

            $tagUpdateArray = array(
                'mainLanguageCode' => $lang,
                'alwaysAvailable' => $alwaysAvail,
                'remoteId' => $remoteId
            );
            $tagUpdateStruct = new \Netgen\TagsBundle\API\Repository\Values\Tags\TagUpdateStruct($tagUpdateArray);

            foreach ($step->dsl['keywords'] as $langCode => $keyword)
            {
                $tagUpdateStruct->setKeyword($keyword, $langCode);
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
     * @param $object
     * @return bool
     *
     * @todo add support for keyword (with language),
     */
    protected function setReferences($object, $step)
    {
        if (!array_key_exists('references', $step->dsl)) {
            return false;
        }

        $references = $this->setReferencesCommon($object, $step->dsl['references']);
        /** @var \Netgen\TagsBundle\API\Repository\Values\Tags\Tag $object */
        $object = $this->insureSingleEntity($object, $references);

        foreach ($references as $reference) {
            switch ($reference['attribute']) {
                case 'id':
                case 'tag_id':
                    $value = $object->id;
                    break;
                case 'always_available':
                    $value = $object->alwaysAvailable;
                    break;
                case 'depth':
                    $value = $object->depth;
                    break;
                case 'main_language_code':
                    $value = $object->mainLanguageCode;
                    break;
                case 'main_tag_id':
                    $value = $object->mainTagId;
                    break;
                case 'modification_date':
                    $value = $object->modificationDate->getTimestamp();
                    break;
                case 'path':
                    $value = $object->pathString;
                    break;
                case 'parent_tag_id':
                    $value = $object->parentTagId;
                    break;
                case 'remote_id':
                    $value = $object->remoteId;
                    break;
                default:
                    throw new \InvalidArgumentException('Tag Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }

    protected function checkTagsBundleInstall()
    {
        if (!$this->tagService)
        {
            throw new \Exception('To manipulate tags you must have NetGen Tags Bundle installed');
        }
    }
}

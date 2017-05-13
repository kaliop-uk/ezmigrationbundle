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
    protected $supportedActions = array('create', 'delete');

    protected $tagService;
    /**
     * @var TagMatcher
     */
    protected $tagMatcher;

    /**
     * @param TagMatcher $tagMatcher
     * @param \Netgen\TagsBundle\Core\SignalSlot\TagsService $tagService
     */
    public function __construct(TagMatcher $tagMatcher, $tagService = null)
    {
        $this->tagMatcher = $tagMatcher;
        $this->tagService = $tagService;
    }

    /**
     * @return mixed
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

    protected function update($step)
    {
        $this->checkTagsBundleInstall();
        throw new \Exception('Tag update is not implemented yet');
    }

    protected function delete($step)
    {
        $this->checkTagsBundleInstall();

        $tagsCollection = $this->matchTags('delete', $step);

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
     */
    protected function setReferences($object, $step)
    {
        if (!array_key_exists('references', $step->dsl)) {
            return false;
        }

        foreach ($step->dsl['references'] as $reference) {
            switch ($reference['attribute']) {
                case 'id':
                    $value = $object->id;
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

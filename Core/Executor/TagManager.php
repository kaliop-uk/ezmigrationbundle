<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\Core\ReferenceResolver\ReferenceHandler;
use Kaliop\eZMigrationBundle\Core\Matcher\TagMatcher;
use Kaliop\eZMigrationBundle\API\Collection\TagCollection;

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
    public function __construct(TagMatcher $tagMatcher, $tagService=null)
    {
        $this->tagMatcher = $tagMatcher;
        $this->tagService = $tagService;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function create()
    {
        $this->checkTagsBundleInstall();

        $alwaysAvail = isset($this->dsl['always_available']) ? $this->dsl['always_available'] : true;
        $parentTagId = 0;
        if(isset($this->dsl['parent_tag_id'])){
            $parentTagId = $this->dsl['parent_tag_id'];
            $parentTagId = $this->referenceResolver->resolveReference($parentTagId);
        }
        $remoteId = isset($this->dsl['remote_id']) ? $this->dsl['remote_id'] : null;

        if (isset($this->dsl['lang'])) {
            $lang = $this->dsl['lang'];
        } elseif (isset($this->dsl['main_language_code'])) {
            // deprecated tag
            $lang = $this->dsl['main_language_code'];
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

        foreach ($this->dsl['keywords'] as $langCode => $keyword)
        {
            $tagCreateStruct->setKeyword($keyword, $langCode);
        }

        $tag = $this->tagService->createTag($tagCreateStruct);
        $this->setReferences($tag);

        return $tag;
    }

    protected function update()
    {
        $this->checkTagsBundleInstall();
        throw new \Exception('Tag update is not implemented yet');
    }

    protected function delete()
    {
        $this->checkTagsBundleInstall();

        $tagsCollection = $this->matchTags('delete');

        foreach ($tagsCollection as $tag) {
            $this->tagService->deleteTag($tag);
        }

        return $tagsCollection;
    }

    protected function checkTagsBundleInstall()
    {
        if (!$this->tagService)
        {
            throw new \Exception('To manipulate tags you must have NetGen Tags Bundle installed');
        }
    }

    /**
     * @param string $action
     * @return TagCollection
     * @throws \Exception
     */
    protected function matchTags($action)
    {
        if (!isset($this->dsl['match'])) {
            throw new \Exception("A match condition is required to $action a Tag.");
        }

        $match = $this->dsl['match'];

        // convert the references passed in the match
        foreach ($match as $condition => $values) {
            if (is_array($values)) {
                foreach ($values as $position => $value) {
                    if ($this->referenceResolver->isReference($value)) {
                        $match[$condition][$position] = $this->referenceResolver->getReferenceValue($value);
                    }
                }
            } else {
                if ($this->referenceResolver->isReference($values)) {
                    $match[$condition] = $this->referenceResolver->getReferenceValue($values);
                }
            }
        }

        return $this->tagMatcher->match($match);
    }

    /**
     * @param $object
     * @return mixed
     */
    protected function setReferences($object)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        foreach ($this->dsl['references'] as $reference) {
            switch ($reference['attribute']) {
                case 'id':
                    $value = $object->id;
                    break;
                default:
                    throw new \InvalidArgumentException('Content Type Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }
    }
}

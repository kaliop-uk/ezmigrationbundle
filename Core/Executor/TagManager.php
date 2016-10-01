<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\Core\ReferenceResolver\ReferenceHandler;
use Netgen\TagsBundle\Core\SignalSlot\TagsService;
use Netgen\TagsBundle\API\Repository\Values\Tags\TagCreateStruct;

class TagManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('tag');

    protected $tagService;

    public function __construct(TagsService $tagService=null)
    {
        $this->tagService = $tagService;
    }

    /**
     * @return mixed
     */
    protected function create()
    {
        $this->checkTagsBundleInstall();

        $alwaysAvail = isset($this->dsl['always_available']) ? $this->dsl['always_available'] : true;
        $parentTagId = isset($this->dsl['parent_tag_id']) ? $this->dsl['parent_tag_id'] : 0;

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
        );
        $tagCreateStruct = new TagCreateStruct($tagCreateArray);

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
        throw new \Exception('Tag delete is not implemented yet');
    }

    protected function checkTagsBundleInstall()
    {
        if (!$this->tagService)
        {
            throw new \Exception('To import tags you must have NetGen Tags Bundle installed');
        }
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

        $referenceHandler = ReferenceHandler::instance();

        foreach ($this->dsl['references'] as $reference) {
            switch ($reference['attribute']) {
                case 'id':
                    $value = $object->id;
                    break;
                default:
                    throw new \InvalidArgumentException('Content Type Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $referenceHandler->addReference($reference['identifier'], $value);
        }
    }
}

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
        $this->loginUser();

        $alwaysAvail = array_key_exists('always_available', $this->dsl) ? $this->dsl['always_available'] : true;
        $parentTagId = array_key_exists('parent_tag_id', $this->dsl) ? $this->dsl['parent_tag_id'] : 0;

        $tagCreateArray = array(
            'parentTagId' => $parentTagId,
            'mainLanguageCode' => $this->dsl['main_language_code'],
            'alwaysAvailable' => $alwaysAvail,
        );
        $tagCreateStruct = new TagCreateStruct($tagCreateArray);

        foreach ($this->dsl['keywords'] as $langCode => $keyword)
        {
            $tagCreateStruct->setKeyword($keyword, $langCode);
        }

        $tag = $this->tagService->createTag($tagCreateStruct);
        $this->setReferences($tag);
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

    /**
     * Helper method to log in a user that can make changes to the system.
     */
    protected function loginUser()
    {
        // Login as admin to be able to post the content. Any other user who has access to
        // create content would be good as well
        $this->repository->setCurrentUser($this->repository->getUserService()->loadUser(self::ADMIN_USER_ID));
    }
}
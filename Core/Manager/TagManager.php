<?php

namespace Kaliop\eZMigrationBundle\Core\Manager;

use Kaliop\eZMigrationBundle\Core\ReferenceHandler;
use Netgen\TagsBundle\Core\SignalSlot\TagsService;
use Netgen\TagsBundle\API\Repository\Values\Tags\TagCreateStruct;
use Symfony\Component\Debug\Exception\ClassNotFoundException;

class TagManager extends AbstractManager
{
    /**
     * @return mixed
     */
    public function create()
    {
        $this->checkTagsBundleInstall();
        $this->loginUser();

        /** @var TagsService $tagsService */
        $tagsService = $this->container->get('ezpublish.api.service.tags');

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

        $tag = $tagsService->createTag($tagCreateStruct);
        $this->setReferences($tag);
    }

    /**
     * @return mixed
     */
    public function update()
    {
        $this->checkTagsBundleInstall();
        throw new \Exception('Tag update is not implemented yet');
    }

    /**
     * @return mixed
     */
    public function delete()
    {
        $this->checkTagsBundleInstall();
        throw new \Exception('Tag delete is not implemented yet');
    }

    protected function checkTagsBundleInstall()
    {
        if ( !$this->container->has('ezpublish.api.service.tags') )
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
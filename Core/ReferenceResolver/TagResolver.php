<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

/**
 * Handles references to tags
 */
class TagResolver extends AbstractResolver
{
    /**
     * Defines the prefix for all reference identifier strings in definitions
     */
    protected $referencePrefixes = array('tag:');

    protected $translationHelper;
    protected $tagService;

    public function __construct($translationHelper, $tagService)
    {
        parent::__construct();

        $this->translationHelper = $translationHelper;
        $this->tagService = $tagService;
    }

    /**
     * @param $stringIdentifier format: tag::<tag_identifier>
     * @return string tag id
     * @throws \Exception
     */
    public function getReferenceValue($stringIdentifier)
    {
        if ($this->tagService == null) {
            throw new \Exception('Netgen TAG Bundle is required to use tag references');
        }

        $identifier = $this->getReferenceIdentifier($stringIdentifier);

        $availableLanguages = $this->translationHelper->getAvailableLanguages();

        if ($this->tagService->getTagsByKeywordCount($identifier, $availableLanguages[0]) > 0) {
            $tags = $this->tagService->loadTagsByKeyword($identifier, $availableLanguages[0], true, 0, 1);
            return $tags[0]->id;
        }

        throw new \Exception('Supplied tag: ' . $identifier . 'Not found.');
    }
}

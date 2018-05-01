<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\Core\Matcher\TagMatcher;

/**
 * Handles references to tags
 *
 * @todo support custom languages
 */
class TagResolver extends AbstractResolver
{
    /**
     * Defines the prefix for all reference identifier strings in definitions
     */
    protected $referencePrefixes = array('tag:');

    protected $tagMatcher;

    /**
     * @param TagMatcher $tagMatcher
     */
    public function __construct(TagMatcher $tagMatcher)
    {
        parent::__construct();

        $this->tagMatcher = $tagMatcher;
    }

    /**
     * @param $stringIdentifier format: tag:<tag_identifier>
     * @return string tag id
     * @throws \Exception
     */
    public function getReferenceValue($stringIdentifier)
    {
        $identifier = $this->getReferenceIdentifier($stringIdentifier);
        $tag = $this->tagMatcher->matchOne(array(TagMatcher::MATCH_TAG_KEYWORD => $identifier));
        return $tag->id;
    }
}

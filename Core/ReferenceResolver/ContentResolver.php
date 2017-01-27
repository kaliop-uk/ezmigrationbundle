<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;

/**
 * Handles references to Contents. Supports remote Ids at the moment.
 * @deprecated
 */
class ContentResolver extends AbstractResolver
{
    /**
     * Constant defining the prefix for all reference identifier strings in definitions
     */
    protected $referencePrefixes = array('content_remote_id:');

    protected $contentMatcher;

    /**
     * @param ContentMatcher $contentMatcher
     */
    public function __construct(ContentMatcher $contentMatcher)
    {
        parent::__construct();

        $this->contentMatcher = $contentMatcher;
    }

    /**
     * @param $stringIdentifier format: content_remote_id:<remote_id>
     * @return string Content id
     * @throws \Exception
     */
    public function getReferenceValue($stringIdentifier)
    {
        $ref = $this->getReferenceIdentifierByPrefix($stringIdentifier);
        switch ($ref['prefix']) {
            case 'content_remote_id:':
                return $this->contentMatcher->matchOne(array(ContentMatcher::MATCH_CONTENT_REMOTE_ID => $ref['identifier']))->id;
        }
    }
}

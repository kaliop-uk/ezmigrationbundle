<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\VersionInfo;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use Kaliop\eZMigrationBundle\API\Collection\VersionInfoCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentVersionMatcher;
use Kaliop\eZMigrationBundle\Core\FieldHandlerManager;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\SectionMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\UserMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateGroupMatcher;
use Kaliop\eZMigrationBundle\Core\Helper\SortConverter;

/**
 * Handles content-version migrations.
 * @todo disallow calling (throw): create, update, generateMigration
 */
class ContentVersionManager extends ContentManager
{
    protected $supportedStepTypes = array('content_version');
    protected $supportedActions = array('delete', 'load');

    protected $versionMatcher;

    public function __construct(
        ContentMatcher $contentMatcher,
        SectionMatcher $sectionMatcher,
        UserMatcher $userMatcher,
        ObjectStateMatcher $objectStateMatcher,
        ObjectStateGroupMatcher $objectStateGroupMatcher,
        FieldHandlerManager $fieldHandlerManager,
        LocationManager $locationManager,
        SortConverter $sortConverter,
        ContentVersionMatcher $versionMatcher
    )
    {
        $this->contentMatcher = $contentMatcher;
        $this->sectionMatcher = $sectionMatcher;
        $this->userMatcher = $userMatcher;
        $this->objectStateMatcher = $objectStateMatcher;
        $this->objectStateGroupMatcher = $objectStateGroupMatcher;
        $this->fieldHandlerManager = $fieldHandlerManager;
        $this->locationManager = $locationManager;
        $this->sortConverter = $sortConverter;
        $this->versionMatcher = $versionMatcher;
    }

    protected function load($step)
    {
        $versionCollection = $this->matchVersions('load', $step);

        $this->setReferences($versionCollection, $step);

        return $versionCollection;
    }

    /**
     * Handles the content delete migration action type
     */
    protected function delete($step)
    {
        $versionCollection = $this->matchVersions('delete', $step);

        $this->setReferences($versionCollection, $step);

        $contentService = $this->repository->getContentService();

        foreach ($versionCollection as $versionInfo) {
            try {
                $contentService->deleteVersion($versionInfo);
            } catch (NotFoundException $e) {
                // Someone else (or even us, by virtue of location tree?) removed the content which we found just a
                // second ago. We can safely ignore this
            }
        }

        return $versionCollection;
    }

    /**
     * @param string $action
     * @return VersionInfoCollection
     * @throws \Exception
     */
    protected function matchVersions($action, $step)
    {
        if (!isset($step->dsl['object_id']) && !isset($step->dsl['remote_id']) && !isset($step->dsl['match'])) {
            throw new \Exception("The id or remote id of an object or a match condition is required to $action a content version");
        }

        if (!isset($step->dsl['match_versions']) && !isset($step->dsl['versions'])) {
            throw new \Exception("A verision match condition is required to $action a content version");
        }

        // Backwards compat

        if (isset($step->dsl['match'])) {
            $match = $step->dsl['match'];
        } else {
            if (isset($step->dsl['object_id'])) {
                $match = array('content_id' => $step->dsl['object_id']);
            } elseif (isset($step->dsl['remote_id'])) {
                $match = array('content_remote_id' => $step->dsl['remote_id']);
            }
        }

        if (isset($step->dsl['match_versions'])) {
            $matchVersions = $step->dsl['match_versions'];
        } else {
            $matchVersions = array(ContentVersionMatcher::MATCH_VERSION => $step->dsl['versions']);
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($match);
        $matchVersions = $this->resolveReferencesRecursively($matchVersions);

        $sort = isset($step->dsl['match_sort']) ? $this->referenceResolver->resolveReference($step->dsl['match_sort']) : array();
        $offset = isset($step->dsl['match_offset']) ? $this->referenceResolver->resolveReference($step->dsl['match_offset']) : 0;
        $limit = isset($step->dsl['match_limit']) ? $this->referenceResolver->resolveReference($step->dsl['match_limit']) : 0;

        return $this->versionMatcher->match($match, $matchVersions, $sort, $offset, $limit);
    }

    /**
     * @param VersionInfo $versionInfo
     * @param array $references
     * @param $step
     * @return array
     *
     * @todo allow setting more refs: creation date, modification date, creator id, langauge codes
     */
    protected function getReferencesValues($versionInfo, array $references, $step)
    {
        $refs = array();

        foreach ($references as $reference) {
            switch ($reference['attribute']) {
                case 'version_no':
                    $value = $versionInfo->versionNo;
                    break;
                case 'version_status':
                    $value = $this->versionStatusToHash($versionInfo->status);
                    break;
                default:
                    // NB: this will generate an error if the user tries to seta  ref to a field value
                    $value = reset(parent::getReferencesValues($versionInfo, array($references), $step));
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }

    protected function versionStatusToHash($status)
    {
        foreach(ContentVersionMatcher::STATUS_MAP as $own => $ez) {
            if ($status == $ez) {
                return $own;
            }
        }

        /// @todo log warning?
        return $status;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\Core\Base\Exceptions\NotFoundException;

/**
 * Handles content-version migrations.
 */
class ContentVersionManager extends ContentManager
{
    protected $supportedStepTypes = array('content_version');
    protected $supportedActions = array('delete');

    /**
     * Handles the content delete migration action type
     */
    protected function delete($step)
    {
        if (!isset($step->dsl['versions'])) {
            throw new \Exception("The 'versions' tag is required to delete content versions");
        }

        $contentCollection = $this->matchContents('delete', $step);

        $this->setReferences($contentCollection, $step);

        $contentService = $this->repository->getContentService();
        $versions = (array)$step->dsl['versions'];

        foreach ($contentCollection as $content) {
            foreach($versions as $versionId) {
                try {
                    if ($versionId < 0) {
                        $contentVersions = $contentService->loadVersions($content->contentInfo);
                        // different eZ kernels apparently sort versions in different order...
                        $sortedVersions = array();
                        foreach($contentVersions as $versionInfo) {
                            $sortedVersions[$versionInfo->versionNo] = $versionInfo;
                        }
                        ksort($sortedVersions);
                        $sortedVersions = array_slice($sortedVersions, 0, $versionId);
                        foreach($sortedVersions as $versionInfo) {
                            $contentService->deleteVersion($versionInfo);
                        }
                    } else {
                        $versionInfo = $contentService->loadVersionInfo($content->contentInfo, $versionId);
                        $contentService->deleteVersion($versionInfo);
                    }
                } catch (NotFoundException $e) {
                    // Someone else removed the content version. We can safely ignore this
                }
            }
        }

        return $contentCollection;
    }
}

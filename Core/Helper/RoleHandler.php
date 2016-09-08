<?php

namespace Kaliop\eZMigrationBundle\Core\Helper;

use eZ\Publish\API\Repository\Repository as eZRepository;
use eZ\Publish\API\Repository\Values\User\Limitation;

/**
 * @todo find a better name for this class?
 */
class RoleHandler
{
    /** @var eZRepository */
    protected $repository;

    protected $translationHelper;

    /** @var array  */
    protected $siteAccessList = array();

    public function __construct(
        eZRepository $repository,
        $translationHelper,
        array $siteAccessList
    )
    {
        $this->repository = $repository;
        $this->translationHelper = $translationHelper;

        foreach ($siteAccessList as $siteAccess) {
            $id = sprintf('%u', crc32($siteAccess));
            $this->siteAccessList[$id] = $siteAccess;
        }
    }

    public function limitationWithIdentifiers(Limitation $limitation)
    {
        return array(
            'identifier' => $limitation->getIdentifier(),
            'values' => $this->getLimitationArrayWithIdentifiers($limitation)
        );
    }

    protected function getLimitationArrayWithIdentifiers(Limitation $limitation)
    {
        $retValues = array();
        $values = $limitation->limitationValues;

        switch ($limitation->getIdentifier()) {
            case 'Section':
                /** @var \eZ\Publish\API\Repository\SectionService $sectionService */
                $sectionService = $this->repository->getSectionService();
                foreach ($values as $value) {
                    $section = $sectionService->loadSection($value);
                    $retValues[] = $section->identifier;
                }
                break;
            case 'SiteAccess':
                foreach($values as $value) {
                    $name = "No Site Access Found";
                    if (array_key_exists($value, $this->siteAccessList)) {
                        $name = $this->siteAccessList[$value];
                    }
                    $retValues[] = $name;
                }
                break;
            case 'ParentClass':
            case 'Class':
                /** @var \eZ\Publish\API\Repository\ContentTypeService $contentTypeService */
                $contentTypeService = $this->repository->getContentTypeService();

                foreach($values as $value) {
                    $contentType = $contentTypeService->loadContentType($value);
                    $retValues[] = $contentType->identifier;
                }
                break;
            case 'NewSection':
                $sectionService = $this->repository->getSectionService();

                foreach ($values as $value) {
                    $section = $sectionService->loadSection($value);
                    $retValues[] = $section->name;
                }
                break;
            default:
                $retValues = $values;
        }

        return $retValues;
    }

    /**
     * Converts human readable limitation values to their numeric counterparts we get the array in 2 parts so the
     * referencing can be completed on it before it's sent to this method.
     *
     * @param $limitationIdentifier
     * @param array $values
     * @return array
     */
    public function convertLimitationToValue($limitationIdentifier, array $values)
    {
        $retValues = array();
        switch ($limitationIdentifier) {
            case 'Node':
                $locationService = $this->repository->getLocationService();
                foreach ($values as $value) {
                    if (!is_int($value)) {
                        $location = $locationService->loadLocationByRemoteId($value);
                        $retValues[] = $location->id;
                    } else {
                        $retValues[] = $value;
                    }
                }
                break;
            case 'Section':
                /** @var \eZ\Publish\API\Repository\SectionService $sectionService */
                $sectionService = $this->repository->getSectionService();
                foreach ($values as $value) {
                    $section = $sectionService->loadSectionByIdentifier($value);
                    $retValues[] = $section->id;
                }
                break;
            case 'SiteAccess':
                foreach($values as $value) {
                    foreach ($this->siteAccessList as $key => $siteAccess) {
                        if ($value == $siteAccess) {
                            $retValues[] = (string)$key;
                        }
                    }
                }
                break;
            case 'ParentClass':
            case 'Class':
                /** @var \eZ\Publish\API\Repository\ContentTypeService $contentTypeService */
                $contentTypeService = $this->repository->getContentTypeService();

                foreach($values as $value) {
                    $contentTypeId = $value;
                    if (!is_int($value)) {
                        $contentType = $contentTypeService->loadContentTypeByIdentifier($value);
                        $contentTypeId = $contentType->id;
                    }
                    $retValues[] = $contentTypeId;
                }
                break;
            case 'NewSection':
                $sectionService = $this->repository->getSectionService();

                foreach ($values as $value) {
                    $sections = $sectionService->loadSections();

                    /** @var \eZ\Publish\API\Repository\Values\Content\Section $section */
                    foreach ($sections as $section) {
                        if ($value == $section->name || $value == $section->identifier || $value == $section->id) {
                            $retValues[] = $section->id;
                        }
                    }
                }
                break;
            default:
                $retValues = $values;
        }

        return $retValues;
    }
}

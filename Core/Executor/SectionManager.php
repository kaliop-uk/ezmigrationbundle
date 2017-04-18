<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\SectionCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\SectionMatcher;

/**
 * Handles section migrations.
 */
class SectionManager extends RepositoryExecutor implements MigrationGeneratorInterface
{
    protected $supportedStepTypes = array('section');

    /** @var SectionMatcher $sectionMatcher */
    protected $sectionMatcher;

    /**
     * @param SectionMatcher $sectionMatcher
     */
    public function __construct(SectionMatcher $sectionMatcher)
    {
        $this->sectionMatcher = $sectionMatcher;
    }

    /**
     * Handles the section create migration action
     */
    protected function create($step)
    {
        $sectionService = $this->repository->getSectionService();

        $sectionCreateStruct = $sectionService->newSectionCreateStruct();

        $sectionCreateStruct->identifier = $step->dsl['identifier'];
        $sectionCreateStruct->name = $step->dsl['name'];

        $section = $sectionService->createSection($sectionCreateStruct);

        $this->setReferences($section, $step);

        return $section;
    }

    /**
     * Handles the section update migration action
     */
    protected function update($step)
    {
        $sectionCollection = $this->matchSections('update', $step);

        if (count($sectionCollection) > 1 && array_key_exists('references', $step->dsl)) {
            throw new \Exception("Can not execute Section update because multiple types match, and a references section is specified in the dsl. References can be set when only 1 section matches");
        }

        $sectionService = $this->repository->getSectionService();
        foreach ($sectionCollection as $key => $section) {
            $sectionUpdateStruct = $sectionService->newSectionUpdateStruct();

            if (isset($step->dsl['identifier'])) {
                $sectionUpdateStruct->identifier = $step->dsl['identifier'];
            }
            if (isset($step->dsl['name'])) {
                $sectionUpdateStruct->name = $step->dsl['name'];
            }

            $section = $sectionService->updateSection($section, $sectionUpdateStruct);

            $sectionCollection[$key] = $section;
        }

        $this->setReferences($sectionCollection, $step);

        return $sectionCollection;
    }

    /**
     * Handles the section delete migration action
     */
    protected function delete($step)
    {
        $sectionCollection = $this->matchSections('delete', $step);

        $sectionService = $this->repository->getSectionService();

        foreach ($sectionCollection as $section) {
            $sectionService->deleteSection($section);
        }

        return $sectionCollection;
    }

    /**
     * @param string $action
     * @return SectionCollection
     * @throws \Exception
     */
    protected function matchSections($action, $step)
    {
        if (!isset($step->dsl['match'])) {
            throw new \Exception("A match condition is required to $action a section");
        }

        $match = $step->dsl['match'];

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        return $this->sectionMatcher->match($match);
    }

    /**
     * Sets references to certain section attributes.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Section|SectionCollection $section
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute
     * @return boolean
     */
    protected function setReferences($section, $step)
    {
        if (!array_key_exists('references', $step->dsl)) {
            return false;
        }

        if ($section instanceof SectionCollection) {
            if (count($section) > 1) {
                throw new \InvalidArgumentException('Section Manager does not support setting references for creating/updating of multiple sections');
            }
            $section = reset($section);
        }

        foreach ($step->dsl['references'] as $reference) {

            switch ($reference['attribute']) {
                case 'section_id':
                case 'id':
                    $value = $section->id;
                    break;
                case 'section_identifier':
                case 'identifier':
                    $value = $section->identifier;
                    break;
                case 'section_name':
                case 'name':
                    $value = $section->name;
                    break;
                default:
                    throw new \InvalidArgumentException('Section Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }

    /**
     * @param array $matchCondition
     * @param string $mode
     * @param array $context
     * @throws \Exception
     * @return array
     */
    public function generateMigration(array $matchCondition, $mode, array $context = array())
    {
        $previousUserId = $this->loginUser($this->getAdminUserIdentifierFromContext($context));
        $sectionCollection = $this->sectionMatcher->match($matchCondition);
        $data = array();

        /** @var \eZ\Publish\API\Repository\Values\Content\Section $section */
        foreach ($sectionCollection as $section) {

            $sectionData = array(
                'type' => reset($this->supportedStepTypes),
                'mode' => $mode,
            );

            switch ($mode) {
                case 'create':
                    $sectionData = array_merge(
                        $sectionData,
                        array(
                            'identifier' => $section->identifier,
                            'name' => $section->name,
                        )
                    );
                    break;
                case 'update':
                    $sectionData = array_merge(
                        $sectionData,
                        array(
                            'match' => array(
                                SectionMatcher::MATCH_SECTION_ID => $section->id
                            ),
                            'identifier' => $section->identifier,
                            'name' => $section->name,
                        )
                    );
                    break;
                case 'delete':
                    $sectionData = array_merge(
                        $sectionData,
                        array(
                            'match' => array(
                                SectionMatcher::MATCH_SECTION_ID => $section->id
                            )
                        )
                    );
                    break;
                default:
                    throw new \Exception("Executor 'section' doesn't support mode '$mode'");
            }

            $data[] = $sectionData;
        }

        $this->loginUser($previousUserId);
        return $data;
    }
}

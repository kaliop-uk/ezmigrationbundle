<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\SectionCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\SectionMatcher;

/**
 * Implements the actions for managing (create/update/delete) section in the system through
 * migrations and abstracts away the eZ Publish Public API.
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
     * Handle the section create migration action
     */
    protected function create()
    {
        $sectionService = $this->repository->getSectionService();

        $sectionCreateStruct = $sectionService->newSectionCreateStruct();

        $sectionCreateStruct->identifier = $this->dsl['identifier'];
        $sectionCreateStruct->name = $this->dsl['name'];

        $section = $sectionService->createSection($sectionCreateStruct);

        $this->setReferences($section);

        return $section;
    }

    /**
     * Handle the section update migration action
     */
    protected function update()
    {
        $sectionCollection = $this->matchSections('update');

        if (count($sectionCollection) > 1 && array_key_exists('references', $this->dsl)) {
            throw new \Exception("Can not execute Section update because multiple types match, and a references section is specified in the dsl. References can be set when only 1 section matches");
        }

        $sectionService = $this->repository->getSectionService();
        foreach ($sectionCollection as $key => $section) {
            $sectionUpdateStruct = $sectionService->newSectionUpdateStruct();

            if (isset($this->dsl['identifier'])) {
                $sectionUpdateStruct->identifier = $this->dsl['identifier'];
            }
            if (isset($this->dsl['name'])) {
                $sectionUpdateStruct->name = $this->dsl['name'];
            }

            $section = $sectionService->updateSection($section, $sectionUpdateStruct);

            $sectionCollection[$key] = $section;
        }

        $this->setReferences($sectionCollection);

        return $sectionCollection;
    }

    /**
     * Handle the section delete migration action
     */
    protected function delete()
    {
        $sectionCollection = $this->matchSections('delete');

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
    protected function matchSections($action)
    {
        if (!isset($this->dsl['match'])) {
            throw new \Exception("A match condition is required to $action a section.");
        }

        $match = $this->dsl['match'];

        // convert the references passed in the match
        foreach ($match as $condition => $values) {
            if (is_array($values)) {
                foreach ($values as $position => $value) {
                    $match[$condition][$position] = $this->referenceResolver->resolveReference($value);
                }
            } else {
                $match[$condition] = $this->referenceResolver->resolveReference($values);
            }
        }

        return $this->sectionMatcher->match($match);
    }

    /**
     * Sets references to certain section attributes.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Section|SectionCollection $section
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute
     * @return boolean
     */
    protected function setReferences($section)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        if ($section instanceof SectionCollection) {
            if (count($section) > 1) {
                throw new \InvalidArgumentException('Section Manager does not support setting references for creating/updating of multiple sections');
            }
            $section = reset($section);
        }

        foreach ($this->dsl['references'] as $reference) {

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
     * @throws \Exception
     * @return array
     */
    public function generateMigration(array $matchCondition, $mode)
    {
        $previousUserId = $this->loginUser(self::ADMIN_USER_ID);
        $sectionCollection = $this->sectionMatcher->match($matchCondition);
        $data = array();

        /** @var \eZ\Publish\API\Repository\Values\Content\Section $section */
        foreach ($sectionCollection as $section) {

            $sectionData = array(
                'type' => 'section',
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
                            'identifier' => $section->identifier,
                            'name' => $section->name,
                        )
                    );
                    // fall through voluntarily
                case 'delete':
                    $sectionData = array_merge(
                        $sectionData,
                        array(
                            'match' => array(
                                'id' => $section->id
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

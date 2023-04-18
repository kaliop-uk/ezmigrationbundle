<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\Section;
use Kaliop\eZMigrationBundle\API\Collection\SectionCollection;
use Kaliop\eZMigrationBundle\API\EnumerableMatcherInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\SectionMatcher;

/**
 * Handles section migrations.
 */
class SectionManager extends RepositoryExecutor implements MigrationGeneratorInterface, EnumerableMatcherInterface
{
    protected $supportedStepTypes = array('section');
    protected $supportedActions = array('create', 'load', 'update', 'delete');

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
        foreach (array('name', 'identifier') as $key) {
            if (!isset($step->dsl[$key])) {
                throw new InvalidStepDefinitionException("The '$key' key is missing in a section creation definition");
            }
        }

        $sectionService = $this->repository->getSectionService();

        $sectionCreateStruct = $sectionService->newSectionCreateStruct();

        $sectionIdentifier = $this->resolveReference($step->dsl['identifier']);
        $sectionCreateStruct->identifier = $sectionIdentifier;
        $sectionCreateStruct->name = $this->resolveReference($step->dsl['name']);

        $section = $sectionService->createSection($sectionCreateStruct);

        $this->setReferences($section, $step);

        return $section;
    }

    protected function load($step)
    {
        $sectionCollection = $this->matchSections('load', $step);

        $this->validateResultsCount($sectionCollection, $step);

        $this->setReferences($sectionCollection, $step);

        return $sectionCollection;
    }

    /**
     * Handles the section update migration action
     */
    protected function update($step)
    {
        $sectionCollection = $this->matchSections('update', $step);

        $this->validateResultsCount($sectionCollection, $step);

        $sectionService = $this->repository->getSectionService();
        foreach ($sectionCollection as $key => $section) {
            $sectionUpdateStruct = $sectionService->newSectionUpdateStruct();

            if (isset($step->dsl['identifier'])) {
                $sectionUpdateStruct->identifier = $this->resolveReference($step->dsl['identifier']);
            }
            if (isset($step->dsl['name'])) {
                $sectionUpdateStruct->name = $this->resolveReference($step->dsl['name']);
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

        $this->validateResultsCount($sectionCollection, $step);

        $this->setReferences($sectionCollection, $step);

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
            throw new InvalidStepDefinitionException("A match condition is required to $action a section");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        $tolerateMisses = isset($step->dsl['match_tolerate_misses']) ? $this->resolveReference($step->dsl['match_tolerate_misses']) : false;

        return $this->sectionMatcher->match($match, $tolerateMisses);
    }

    /**
     * @param Section $section
     * @param array $references the definitions of the references to set
     * @throws InvalidStepDefinitionException
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($section, array $references, $step)
    {
        $refs = array();

        foreach ($references as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
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
                    throw new InvalidStepDefinitionException('Section Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }

    /**
     * @param array $matchConditions
     * @param string $mode
     * @param array $context
     * @return array
     * @throws \Exception
     */
    public function generateMigration(array $matchConditions, $mode, array $context = array())
    {
        $data = array();
        $previousUserId = $this->loginUser($this->getAdminUserIdentifierFromContext($context));
        try {
            $sectionCollection = $this->sectionMatcher->match($matchConditions);

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
                        throw new InvalidStepDefinitionException("Executor 'section' doesn't support mode '$mode'");
                }

                $data[] = $sectionData;
            }

            $this->loginUser($previousUserId);
        } catch (\Exception $e) {
            $this->loginUser($previousUserId);
            throw $e;
        }

        return $data;
    }

    /**
     * @return string[]
     */
    public function listAllowedConditions()
    {
        return $this->sectionMatcher->listAllowedConditions();
    }
}

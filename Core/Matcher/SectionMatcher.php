<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\Content\Section;
use Kaliop\eZMigrationBundle\API\Collection\SectionCollection;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

class SectionMatcher extends AbstractMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_SECTION_ID = 'section_id';
    const MATCH_SECTION_IDENTIFIER = 'section_identifier';

    protected $allowedConditions = array(
        self::MATCH_SECTION_ID, self::MATCH_SECTION_IDENTIFIER,
        // aliases
        'id', 'identifier'
    );
    protected $returns = 'Role';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return SectionCollection
     */
    public function match(array $conditions)
    {
        return $this->matchSection($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return SectionCollection
     */
    public function matchSection(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_SECTION_ID:
                   return new SectionCollection($this->findSectionsById($values));

                case 'identifier':
                case self::MATCH_SECTION_IDENTIFIER:
                    return new SectionCollection($this->findSectionsByIdentifier($values));
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_SECTION_ID => $key);
        }
        return array(self::MATCH_SECTION_IDENTIFIER => $key);
    }

    /**
     * @param int[] $sectionIds
     * @return Section[]
     */
    protected function findSectionsById(array $sectionIds)
    {
        $sections = [];

        foreach ($sectionIds as $sectionId) {
            // return unique contents
            $section = $this->repository->getSectionService()->loadSection($sectionId);
            $sections[$section->id] = $section;
        }

        return $sections;
    }

    /**
     * @param string[] $sectionIdentifiers
     * @return Section[]
     */
    protected function findSectionsByIdentifier(array $sectionIdentifiers)
    {
        $sections = [];

        foreach ($sectionIdentifiers as $sectionIdentifier) {
            // return unique contents
            $section = $this->repository->getSectionService()->loadSectionByIdentifier($sectionIdentifier);
            $sections[$section->id] = $section;
        }

        return $sections;
    }
}

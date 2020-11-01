<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\TagMatcher;

class EzTags extends AbstractFieldHandler implements FieldValueImporterInterface
{
    protected $tagMatcher;

    public function __construct(TagMatcher $tagMatcher)
    {
        $this->tagMatcher = $tagMatcher;
    }

    /**
     * Creates a value object to use as the field value when setting an eztags field type.
     *
     * @param array $fieldValue
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return \Netgen\TagsBundle\Core\FieldType\Tags\Value
     * @throws \Exception
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        $tags = array();
        foreach ($fieldValue as $def)
        {
            if (!is_array($def) || count($def) != 1) {
                throw new InvalidStepDefinitionException('Definition of EzTags field is incorrect: each element of the tags array must be an array with one element');
            }

            $identifier = reset($def);
            $type = key($def);

            $identifier = $this->referenceResolver->resolveReference($identifier);

            foreach ($this->tagMatcher->match(array($type => $identifier)) as $id => $tag) {
                $tags[$id] = $tag;
            }
        }

        return new \Netgen\TagsBundle\Core\FieldType\Tags\Value(array_values($tags));
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\TagMatcher;
use Netgen\TagsBundle\Core\FieldType\Tags\Value as TagsValue;

class EzTags extends AbstractComplexField implements ComplexFieldInterface
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
     * @return TagsValue
     * @throws \Exception
     */
    public function createValue($fieldValue, array $context = array())
    {
        $tags = array();
        foreach ($fieldValue as $def)
        {
            if (!is_array($def) || count($def) != 1) {
                throw new \Exception('Definition of EzTags field is incorrect: each element of the tags array must be an array with one element');
            }

            $identifier = reset($def);
            $type = key($def);

            $identifier = $this->referenceResolver->resolveReference($identifier);

            foreach ($this->tagMatcher->match(array($type => $identifier)) as $id => $tag) {
                $tags[$id] = $tag;
            }
        }

        return new TagsValue(array_values($tags));
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Publish\Core\FieldType\Author\Value as AuthorValue;
use eZ\Publish\Core\FieldType\Author\Author;
use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;

/**
 * @todo is this needed at all ?
 */
class EzAuthor extends AbstractComplexField implements ComplexFieldInterface
{
    /**
     * Creates a value object to use as the field value when setting an author field type.
     *
     * @param array $fieldValueArray The definition of teh field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return AuthorValue
     */
    public function createValue(array $fieldValueArray, array $context = array())
    {
        $authors = array();

        /// @deprecated
        if (isset($fieldValueArray['authors'])) {
            foreach($fieldValueArray['authors'] as $author) {
                $authors[] = new Author($author);
            }
        } else if (is_array($fieldValueArray)) {
            /// same as what fromHash() does, really
            foreach($fieldValueArray as $author) {
                $authors[] = new Author($author);
            }
        }

        return new AuthorValue($authors);
    }
}

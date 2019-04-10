<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\Core\FieldType\Author\Value as AuthorValue;
use eZ\Publish\Core\FieldType\Author\Author;
use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;

/**
 * @todo is this needed at all ?
 */
class EzAuthor extends AbstractFieldHandler implements FieldValueImporterInterface
{
    /**
     * Creates a value object to use as the field value when setting an author field type.
     *
     * @param array $fieldValue The definition of the field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return AuthorValue
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        $authors = array();

        /// @deprecated
        if (isset($fieldValue['authors'])) {
            foreach ($fieldValue['authors'] as $author) {
                $author = $this->referenceResolver->resolveReference($author);
                $authors[] = new Author($author);
            }
        } else if (is_array($fieldValue)) {
            /// same as what fromHash() does, really
            foreach ($fieldValue as $author) {
                $author = $this->referenceResolver->resolveReference($author);
                $authors[] = new Author($author);
            }
        }

        return new AuthorValue($authors);
    }
}

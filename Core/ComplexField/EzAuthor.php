<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Publish\Core\FieldType\Author\Value as AuthorValue;
use eZ\Publish\Core\FieldType\Author\AuthorCollection;
use eZ\Publish\Core\FieldType\Author\Author;
use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;

class EzAuthor extends AbstractComplexField implements ComplexFieldInterface
{
    /**
     * Creates a value object to use as the field value when setting an author field type.
     *
     * @return AuthorValue
     */
    public function createValue()
    {
        $authorData = $this->fieldValueArray;

        $authors = array();

        foreach( $authorData['authors'] as $author ) {
            $authors[] = new Author($author);
        }

        $authorValue = new AuthorValue($authors);

        return $authorValue;
    }
}

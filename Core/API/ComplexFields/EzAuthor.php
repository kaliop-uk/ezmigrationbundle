<?php
/**
 * Created by PhpStorm.
 * User: phalasz
 * Date: 31/03/16
 * Time: 12:00
 */

namespace Kaliop\eZMigrationBundle\Core\API\ComplexFields;

use eZ\Publish\Core\FieldType\Author\Value as AuthorValue;
use eZ\Publish\Core\FieldType\Author\AuthorCollection;
use eZ\Publish\Core\FieldType\Author\Author;

class EzAuthor extends AbstractComplexField
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
<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use Kaliop\eZMigrationBundle\API\FieldDefinitionConverterInterface;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;

/**
 * @todo make fieldSettings independent from version of ezmatrix in use, by converting them from a common format
 */
class EzMatrix extends AbstractFieldHandler implements FieldValueConverterInterface
{
    /**
     * @return bool
     */
    protected function usingLegacyFieldType()
    {
        return class_exists('EzSystems\MatrixBundle\FieldType\Matrix\Type');
    }

    public function hashToFieldValue($fieldValue, array $context = array())
    {
        /// @todo resolve refs ?

        if ($this->usingLegacyFieldType()) {
            $rows = array();
            $columns = array();
            // BC with migrations which used to be valid before version 5.14 - see issue #250
            if (count($fieldValue) === 2 && isset($fieldValue['columns']) && isset($fieldValue['rows'])) {
                $colDefs = $fieldValue['columns'];
                $fieldValue = $fieldValue['rows'];
            } else {
                // We _have_ to pass in column defs, otherwise we hit a bug in ColumnCollection::createFromNames :-(
                $colIdentifiers = array();
                foreach ($fieldValue as $row) {
                    $colIdentifiers = array_merge($colIdentifiers, array_keys($row));
                }
                $colDefs = array();
                $i = 1;
                // the fix: array_unique !
                foreach (array_unique($colIdentifiers) as $colidentifier) {
                    /// @todo if $colidentifier looks like a col nane, use it as such, and create the identifier from it.
                    ///       Or, even better, grab column defs from the contentType field definition
                    $colDefs[] = array('id' => $colidentifier, 'name' => ucfirst($colidentifier), 'num' => $i++);
                }
            }
            foreach ($fieldValue as $data) {
                $rows[] = new \EzSystems\MatrixBundle\FieldType\Matrix\Row($data);
            }
            foreach ($colDefs as $data) {
                $columns[] = new \EzSystems\MatrixBundle\FieldType\Matrix\Column($data);
            }
            return new \EzSystems\MatrixBundle\FieldType\Matrix\Value($rows, $columns);
        } else {
            $rows = array();
            foreach ($fieldValue as $data) {
                $rows[] = new \EzSystems\EzPlatformMatrixFieldtype\FieldType\Value\Row($data);
            }
            return new \EzSystems\EzPlatformMatrixFieldtype\FieldType\Value($rows);
        }
    }

    public function fieldValueToHash($fieldValue, array $context = array())
    {
        $data = array();

        if ($this->usingLegacyFieldType()) {
            foreach ($fieldValue->rows as $row) {
                $data[] = $row->toArray();
            }
        } else {
            foreach ($fieldValue->getRows() as $row) {
                $data[] = $row->getCells();
            }
        }

        return $data;
    }
}

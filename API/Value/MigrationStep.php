<?php

namespace Kaliop\eZMigrationBundle\API\Value;

/**
 * @property-read string $type
 * @property-read array $dsl
 * @property-read array $context
 */
class MigrationStep extends AbstractValue
{
    protected $type;
    protected $dsl;
    protected $context;

    public function __construct($type, array $dsl = array(), array $context = array())
    {
        $this->type = $type;
        $this->dsl = $dsl;
        $this->context = $context;
    }

    /**
     * Allow the class to be serialized to php using var_export
     * @param array $data
     * @return static
     */
    public static function __set_state(array $data)
    {
        return new static(
            $data['type'],
            $data['dsl'],
            $data['context']
        );
    }
}

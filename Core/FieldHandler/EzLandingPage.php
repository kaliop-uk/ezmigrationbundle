<?php
namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use EzSystems\LandingPageFieldTypeBundle\FieldType\LandingPage\Value as PageValue;
use EzSystems\LandingPageFieldTypeBundle\FieldType\LandingPage\Type as PageType;
use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;
use EzSystems\LandingPageFieldTypeBundle\FieldType\LandingPage\XmlConverter;

class EzLandingPage extends AbstractFieldHandler implements FieldValueImporterInterface
{

    /** @var PageType $pageType */
    protected $pageType;

    /** @var XmlConverter $xmlConverter */
    protected $xmlConverter;

    public function __construct(PageType $pageType, XmlConverter $xmlConverter)
    {
        $this->pageType = $pageType;
        $this->xmlConverter = $xmlConverter;
    }

    /**
     * Creates a value object to use as the field value when setting an ez page field type.
     *
     * @param XML string $fieldValue The definition of the field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return PageValue
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        $page = $this->xmlConverter->fromXml($fieldValue);
        $value = new PageValue($page);
        return $value;
    }
}